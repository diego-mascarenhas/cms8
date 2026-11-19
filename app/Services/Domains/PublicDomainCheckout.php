<?php

namespace App\Services\Domains;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Server;
use App\Models\Service;
use App\Models\ServiceSync;
use App\Models\Team;
use App\Models\User;
use App\Services\Hosting\FlexHostingProvisioner;
use App\Services\TeamModulesByPricingPlanSyncer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PublicDomainCheckout
{
    public function __construct(
        private PorkbunRegistrar $porkbun,
        private DomainCheckoutSessions $stripe,
        private FlexHostingProvisioner $hosting,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: string, country: string, tax_id: string, business_name?: string|null}  $customer
     * @return array{checkout_url: string, plan: string, plan_amount: int, domain: string, price: float, currency: string, account: string}
     */
    public function start(Team $registrarTeam, array $customer, string $domain, string $successUrl, string $cancelUrl, ?string $referralCode = null): array
    {
        $quote = $this->porkbun->quote($registrarTeam, $domain);

        if (! $quote['available'] || $quote['price'] === null)
        {
            throw new RuntimeException('Ese dominio no está disponible.');
        }

        $amountCents = (int) round($quote['price'] * 100);

        if ($amountCents < 50)
        {
            throw new RuntimeException('El importe del dominio no es válido.');
        }

        $account = $this->ensureAccount($customer);
        $enterprise = $account['enterprise'];
        $referrer = $this->rememberReferrer($account['team'], $referralCode);
        $session = $this->stripe->create([
            'customer' => $enterprise->getStripeCustomerId(),
            'mode' => 'subscription',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $amountCents,
                    'recurring' => ['interval' => 'year'],
                    'product_data' => [
                        'name' => 'Dominio '.$quote['domain'],
                        'description' => 'Plan Flex incluido, sin cuota mensual.',
                        'metadata' => [
                            'type' => 'domain',
                            'plan' => 'flex',
                        ],
                    ],
                ],
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $this->metadata($quote['domain'], $enterprise, $account['user'], $referrer),
            'subscription_data' => [
                'metadata' => $this->metadata($quote['domain'], $enterprise, $account['user'], $referrer),
            ],
        ]);

        $url = trim((string) ($session->url ?? ''));

        if ($url === '')
        {
            throw new RuntimeException('No se pudo abrir el pago.');
        }

        return [
            'checkout_url' => $url,
            'plan' => 'Flex',
            'plan_amount' => 0,
            'domain' => $quote['domain'],
            'price' => (float) $quote['price'],
            'currency' => 'USD',
            'account' => $account['created'] ? 'created' : 'existing',
        ];
    }

    /**
     * @return array{domain: string, plan: string, price: float|null, currency: string, message: string, hosting: array{username: string, password: string|null, mailbox: string}|null}
     */
    public function complete(Team $registrarTeam, string $sessionId): array
    {
        $session = $this->stripe->retrieve($sessionId);
        $metadata = $this->metadataArray($session->metadata ?? []);
        $domain = trim((string) ($metadata['domain'] ?? ''));
        $enterpriseId = (int) ($metadata['enterprise_id'] ?? 0);
        $subscriptionId = $this->subscriptionId($session);
        $paid = ($session->payment_status ?? '') === 'paid' || $this->subscriptionActive($session);

        if (($metadata['type'] ?? '') !== 'domain' || ($metadata['plan'] ?? '') !== 'flex' || $domain === '' || $enterpriseId === 0 || $subscriptionId === '')
        {
            throw new RuntimeException('El pago no corresponde a un registro de dominio.');
        }

        if (! $paid)
        {
            throw new RuntimeException('El pago todavía no está confirmado.');
        }

        $existing = ServiceSync::query()->where('stripe_id', $subscriptionId)->first();

        if ($existing !== null)
        {
            return $this->alreadyDone($existing, $domain);
        }

        $enterprise = Enterprise::withoutGlobalScope('team')->find($enterpriseId);

        if ($enterprise === null)
        {
            throw new RuntimeException('No encontramos la empresa del pago.');
        }

        $registered = $this->porkbun->register($registrarTeam, $domain);
        $sync = $this->storeSync($enterprise, $subscriptionId, $registered);
        $serviceUserId = (int) ($metadata['user_id'] ?? $enterprise->creator_id ?? 0);
        $this->storeService($serviceUserId, $enterprise, $sync->id, $registered, $subscriptionId);

        $hosting = $this->provisionFlex($enterprise, $registered['domain'], $serviceUserId);

        return [
            'domain' => $registered['domain'],
            'plan' => 'Flex',
            'price' => $registered['price'],
            'currency' => 'USD',
            'message' => $registered['message'],
            'hosting' => $hosting,
        ];
    }

    /**
     * @param  array{name: string, email: string, phone: string, country: string, tax_id: string, business_name?: string|null}  $customer
     * @return array{user: User, team: Team, enterprise: Enterprise, created: bool}
     */
    private function ensureAccount(array $customer): array
    {
        $email = strtolower(trim($customer['email']));
        $user = User::query()->where('email', $email)->first();
        $created = false;

        if ($user === null)
        {
            $user = User::query()->create([
                'name' => $customer['name'],
                'email' => $email,
                'password' => Hash::make(Str::password(24)),
            ]);
            $created = true;
            $this->sendPasswordReset($email);
        }

        $existing = $this->administeredStripeAccount($user);

        if ($existing !== null)
        {
            return [
                'user' => $user,
                'team' => $existing['team'],
                'enterprise' => $existing['enterprise'],
                'created' => false,
            ];
        }

        $team = Team::query()->where('user_id', $user->id)->orderBy('id')->first();

        if ($team === null)
        {
            $team = $this->createTeam($user, $customer);
            $created = true;
        }

        $enterprise = Enterprise::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('code', 'like', 'cus_%')
            ->orderBy('id')
            ->first();

        if ($enterprise === null)
        {
            $enterprise = $this->createEnterprise($team, $user, $customer);
            $created = true;
        }

        return [
            'user' => $user,
            'team' => $team,
            'enterprise' => $enterprise,
            'created' => $created,
        ];
    }

    /**
     * @return array{team: Team, enterprise: Enterprise}|null
     */
    private function administeredStripeAccount(User $user): ?array
    {
        $teamIds = Team::query()->where('user_id', $user->id)->pluck('id');
        $memberIds = DB::table('team_user')
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->pluck('team_id');
        $ids = $teamIds->merge($memberIds)->unique()->filter()->values();

        if ($ids->isEmpty())
        {
            return null;
        }

        $enterprise = Enterprise::withoutGlobalScope('team')
            ->whereIn('team_id', $ids)
            ->where('code', 'like', 'cus_%')
            ->orderBy('id')
            ->first();

        if ($enterprise === null)
        {
            return null;
        }

        $team = Team::query()->find($enterprise->team_id);

        if ($team === null)
        {
            return null;
        }

        return [
            'team' => $team,
            'enterprise' => $enterprise,
        ];
    }

    /**
     * @param  array{name: string, email: string, phone: string, country: string, tax_id: string, business_name?: string|null}  $customer
     */
    private function createTeam(User $user, array $customer): Team
    {
        $name = trim((string) ($customer['business_name'] ?? ''));

        if ($name === '')
        {
            $name = $customer['name'];
        }

        $team = $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => $name,
            'personal_team' => true,
        ]));

        if ($user->current_team_id === null)
        {
            $user->forceFill(['current_team_id' => $team->id])->save();
        }

        try
        {
            app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan(
                $team,
                (string) config('humano_pricing.registration_team_plan_slug', 'hunter'),
            );
        } catch (Throwable $exception)
        {
            report($exception);
        }

        return $team;
    }

    /**
     * @param  array{name: string, email: string, phone: string, country: string, tax_id: string, business_name?: string|null}  $customer
     */
    private function createEnterprise(Team $team, User $user, array $customer): Enterprise
    {
        $typeId = (int) DB::table('enterprise_types')->orderBy('id')->value('id');
        $statusId = (int) DB::table('enterprise_statuses')->orderBy('id')->value('id');

        if ($typeId === 0 || $statusId === 0)
        {
            throw new RuntimeException('No se pudo preparar la empresa.');
        }

        $name = trim((string) ($customer['business_name'] ?? ''));

        if ($name === '')
        {
            $name = $customer['name'];
        }

        $stripeId = $this->stripe->createCustomer([
            'name' => $name,
            'email' => strtolower(trim($customer['email'])),
            'phone' => $customer['phone'],
            'address' => ['country' => strtoupper($customer['country'])],
            'metadata' => [
                'tax_id' => $customer['tax_id'],
                'source' => 'revision-alpha-domain',
            ],
        ]);

        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'type_id' => $typeId,
            'status_id' => $statusId,
            'name' => $name,
            'email' => strtolower(trim($customer['email'])),
            'phone' => $customer['phone'],
            'country' => strtoupper($customer['country']),
            'creator_id' => $user->id,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        $enterprise->setStripeCustomerId($stripeId);
        $enterprise->save();

        return $enterprise;
    }

    private function rememberReferrer(Team $payer, ?string $referralCode): ?string
    {
        $existing = trim((string) ($payer->referred_by ?? ''));
        if ($existing !== '')
        {
            return str_starts_with(strtolower($existing), 'cus_') ? $existing : null;
        }

        $referralCode = trim((string) $referralCode);
        if (! str_starts_with(strtolower($referralCode), 'cus_') || strlen($referralCode) > 64)
        {
            return null;
        }

        $referrer = Team::findByStripeCustomerId($referralCode);
        if ($referrer === null || (int) $referrer->id === (int) $payer->id)
        {
            return null;
        }

        $payer->forceFill(['referred_by' => $referralCode])->save();

        return $referralCode;
    }

    /**
     * @return array<string, string>
     */
    private function metadata(string $domain, Enterprise $enterprise, User $user, ?string $referrer = null): array
    {
        $metadata = [
            'type' => 'domain',
            'plan' => 'flex',
            'domain' => $domain,
            'enterprise_id' => (string) $enterprise->id,
            'user_id' => (string) $user->id,
        ];

        if ($referrer !== null && $referrer !== '')
        {
            $metadata['referred_by'] = $referrer;
        }

        return $metadata;
    }

    /**
     * @return array<string, string>
     */
    private function metadataArray(mixed $metadata): array
    {
        if (is_array($metadata))
        {
            return $metadata;
        }

        if (is_object($metadata))
        {
            return (array) $metadata;
        }

        return [];
    }

    private function subscriptionId(object $session): string
    {
        $subscription = $session->subscription ?? null;

        if (is_string($subscription))
        {
            return trim($subscription);
        }

        if (is_object($subscription))
        {
            return trim((string) ($subscription->id ?? ''));
        }

        return '';
    }

    private function subscriptionActive(object $session): bool
    {
        $subscription = $session->subscription ?? null;

        if (! is_object($subscription))
        {
            return false;
        }

        return in_array(strtolower((string) ($subscription->status ?? '')), ['active', 'trialing'], true);
    }

    /**
     * @param  array{domain: string, cost: float|null, price: float|null, currency: string, markup_percent: float}  $registered
     */
    private function storeSync(Enterprise $enterprise, string $subscriptionId, array $registered): ServiceSync
    {
        $amount = round((float) $registered['price'], 2);

        return ServiceSync::query()->updateOrCreate(
            [
                'stripe_id' => $subscriptionId,
                'team_id' => $enterprise->team_id,
            ],
            [
                'provider' => 'stripe',
                'type' => 'sell',
                'customer_id' => $enterprise->getStripeCustomerId(),
                'customer_email' => $enterprise->email,
                'customer_name' => $enterprise->name,
                'status' => 'active',
                'collection_method' => 'charge_automatically',
                'plan_name' => 'Dominio anual '.$registered['domain'],
                'plan_interval' => 'year',
                'plan_interval_count' => 1,
                'quantity' => 1,
                'price_currency' => 'USD',
                'unit_amount' => $amount,
                'amount_subtotal' => $amount,
                'amount_total' => $amount,
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
                'cancel_at_period_end' => false,
                'last_synced_at' => now(),
                'data' => [
                    'type' => 'domain',
                    'plan' => 'flex',
                    'domain' => $registered['domain'],
                ],
            ],
        );
    }

    /**
     * @param  array{domain: string, cost: float|null, price: float|null, markup_percent: float}  $registered
     */
    private function storeService(int $userId, Enterprise $enterprise, int $syncId, array $registered, string $subscriptionId): void
    {
        $categoryId = Category::query()->where('name', 'like', '%Dominio%')->value('id');

        if ($categoryId === null)
        {
            $categoryId = Category::query()->create([
                'name' => 'Dominios',
                'team_id' => $enterprise->team_id,
                'status' => true,
            ])->id;
        }

        Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'subscription_id' => $syncId,
            'category_id' => $categoryId,
            'operation' => 'sell',
            'description' => 'Dominio '.$registered['domain'].' (anual)',
            'data' => [
                'domain' => $registered['domain'],
                'period' => 'annual',
                'plan' => 'flex',
                'cost' => $registered['cost'],
                'price' => $registered['price'],
                'currency' => 'USD',
                'markup_percent' => $registered['markup_percent'],
                'stripe_subscription' => $subscriptionId,
            ],
            'responsible_id' => $userId > 0 ? $userId : null,
            'status' => 4,
            'frequency' => 12,
            'next_billing' => now()->addYear()->toDateString(),
        ]);
    }

    /**
     * @return array{domain: string, plan: string, price: float|null, currency: string, message: string, hosting: null}
     */
    private function alreadyDone(ServiceSync $sync, string $domain): array
    {
        return [
            'domain' => $domain,
            'plan' => 'Flex',
            'price' => $sync->unit_amount !== null ? (float) $sync->unit_amount : null,
            'currency' => 'USD',
            'message' => 'El dominio ya estaba registrado.',
            'hosting' => null,
        ];
    }

    /**
     * @return array{username: string, password: string|null, mailbox: string}|null
     */
    private function provisionFlex(Enterprise $enterprise, string $domain, int $userId): ?array
    {
        $server = $this->provisioningServer();

        if ($server === null)
        {
            return null;
        }

        $user = User::query()->find($userId) ?? User::query()->find($enterprise->creator_id);

        if ($user === null)
        {
            return null;
        }

        $team = Team::query()->find($enterprise->team_id);

        if ($team !== null)
        {
            $user->current_team_id = $team->id;
            $user->setRelation('currentTeam', $team);
        }

        $username = $this->username($domain);

        try
        {
            $created = $this->hosting->provision($user, $server, $enterprise->id, $domain, $username, ['info']);
        } catch (RuntimeException $exception)
        {
            report($exception);

            return null;
        }

        return [
            'username' => $username,
            'password' => $created['password'],
            'mailbox' => 'info@'.$domain,
        ];
    }

    private function provisioningServer(): ?Server
    {
        $servers = Server::query()
            ->where('control_panel', 'cpanel')
            ->orderBy('id')
            ->get()
            ->filter(fn (Server $server) => $server->hasToken())
            ->values();

        $wanted = strtolower(trim((string) config('humano_hosting.provision_server', 'odin')));

        if ($wanted === '')
        {
            return $servers->first();
        }

        return $servers->first(function (Server $server) use ($wanted)
        {
            $name = strtolower(trim((string) $server->name));
            $host = strtolower((string) $server->hostname);

            return $name === $wanted || $host === $wanted || str_starts_with($host, $wanted.'.');
        });
    }

    private function username(string $domain): string
    {
        $label = explode('.', $domain)[0] ?? $domain;
        $username = preg_replace('/[^a-z0-9_]/', '', str_replace(['-', '.'], '', strtolower($label))) ?? '';

        if ($username === '' || preg_match('/^[a-z]/', $username) !== 1)
        {
            $username = 'u'.$username;
        }

        $username = substr($username, 0, 16);

        return $username !== '' ? $username : 'site';
    }

    private function sendPasswordReset(string $email): void
    {
        try
        {
            Password::sendResetLink(['email' => $email]);
        } catch (Throwable $exception)
        {
            report($exception);
        }
    }
}
