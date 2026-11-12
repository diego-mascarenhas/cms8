<?php

namespace App\Http\Controllers\Api;

use App\Helpers\DnsHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeHostingPlanRequest;
use App\Http\Requests\CreateHostingSessionRequest;
use App\Http\Requests\StoreApiHostingRequest;
use App\Http\Requests\StoreHostingEmailRequest;
use App\Http\Requests\UpdateHostingEmailPasswordRequest;
use App\Http\Requests\UpdateHostingEmailQuotaRequest;
use App\Http\Requests\UpdateHostingPasswordRequest;
use App\Jobs\RefreshDomainDataJob;
use App\Models\Domain;
use App\Models\Server;
use App\Services\ControlPanel\ControlPanelManager;
use App\Services\ControlPanel\CpanelConnector;
use App\Services\Hosting\DomainCpanelPasswordService;
use App\Services\Hosting\DomainMailboxPasswordService;
use App\Services\Hosting\DomainMailboxQuotaService;
use App\Services\Hosting\DomainRuntimeDetector;
use App\Services\Hosting\FlexHostingProvisioner;
use App\Services\Hosting\HostingFailureNotice;
use App\Services\Hosting\HostingPlanCatalog;
use App\Services\Hosting\HostingSubscriptionSync;
use App\Support\HostingAccountPresenter;
use App\Support\MailExchange;
use App\Support\SearchNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class HostingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->canAccessInfrastructure())
        {
            abort(403);
        }

        $accounts = $this->teamAccounts($request);
        $total = (clone $accounts)->count();
        $active = (clone $accounts)->where('suspended', false)->count();
        $suspended = (clone $accounts)->where('suspended', true)->count();

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        if ($search !== '')
        {
            SearchNormalizer::applyColumnsNavbarConditions($accounts, ['domain', 'username', 'plan'], $search);
        }

        if ($status === 'active')
        {
            $accounts->where('suspended', false);
        } elseif ($status === 'suspended')
        {
            $accounts->where('suspended', true);
        }

        $perPage = min(50, max(1, (int) $request->query('per_page', 25)));
        $page = $accounts->orderBy('domain')->paginate($perPage);

        return response()->json([
            'success' => true,
            'nameservers' => $this->provisioningNameservers(),
            'stats' => [
                'total' => $total,
                'active' => $active,
                'suspended' => $suspended,
            ],
            'data' => $page->through(fn (Domain $domain) => [
                'id' => $domain->id,
                'domain' => $domain->domain,
                'username' => $domain->username,
                'plan' => $domain->plan,
                'site_type' => $domain->site_type,
                'php_version' => $domain->php_version,
                'suspended' => (bool) $domain->suspended,
                'server' => $domain->server?->name,
            ]),
        ]);
    }

    public function show(Request $request, Domain $domain, HostingPlanCatalog $plans, DomainRuntimeDetector $runtime): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $runtime->fillMissing($domain);

        return response()->json([
            'success' => true,
            'data' => $this->account($domain, $plans),
        ]);
    }

    public function updatePlan(ChangeHostingPlanRequest $request, Domain $domain, HostingPlanCatalog $plans, HostingSubscriptionSync $subscriptions): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $result = $plans->change($domain, (string) $request->validated('plan'));

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        $domain = $domain->fresh();
        $note = $subscriptions->afterPlanChange($request->user()->currentTeam, $domain);
        $message = trim($result['message'].($note !== '' ? ' '.$note : ''));

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->account($domain, $plans),
        ]);
    }

    public function updatePassword(UpdateHostingPasswordRequest $request, Domain $domain, DomainCpanelPasswordService $passwords): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $result = $passwords->resetAndOptionallyNotify(
            $request->user(),
            $domain,
            null,
            DomainCpanelPasswordService::NOTIFY_NONE,
            (string) $request->validated('password'),
        );

        return $this->passwordResponse($result);
    }

    public function createSession(CreateHostingSessionRequest $request, Domain $domain, CpanelConnector $cpanel): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $server = $domain->server;

        if ($server === null || $server->control_panel !== 'cpanel' || ! $server->hasToken())
        {
            return response()->json([
                'success' => false,
                'message' => 'Este hosting no tiene cPanel configurado.',
            ], 422);
        }

        $service = $request->validated('service') === 'webmail' ? 'webmaild' : 'cpaneld';
        $result = $cpanel->createUserSession($server, (string) $domain->username, $service);

        if (! ($result['success'] ?? false) || ! filled($result['url'] ?? null))
        {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'No se pudo abrir la sesión.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'url' => $result['url'],
        ]);
    }

    public function updateEmailPassword(UpdateHostingEmailPasswordRequest $request, Domain $domain, DomainMailboxPasswordService $mailboxes): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $email = (string) $request->validated('email');
        $suffix = '@'.strtolower((string) $domain->domain);

        if (! str_ends_with($email, $suffix))
        {
            return response()->json([
                'success' => false,
                'message' => 'Esa cuenta no pertenece a este dominio.',
            ], 422);
        }

        $result = $mailboxes->resetAndOptionallyNotify(
            $request->user(),
            $domain,
            $email,
            null,
            DomainCpanelPasswordService::NOTIFY_NONE,
            (string) $request->validated('password'),
        );

        return $this->passwordResponse($result);
    }

    public function updateEmailQuota(UpdateHostingEmailQuotaRequest $request, Domain $domain, DomainMailboxQuotaService $quotas): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $email = (string) $request->validated('email');
        $suffix = '@'.strtolower((string) $domain->domain);

        if (! str_ends_with($email, $suffix))
        {
            return response()->json([
                'success' => false,
                'message' => 'Esa cuenta no pertenece a este dominio.',
            ], 422);
        }

        $quota = $request->validated('quota_mb');
        $result = $quotas->increase(
            $domain,
            $email,
            is_numeric($quota) ? (int) $quota : null,
            (bool) $request->boolean('unlimited'),
        );

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    public function storeEmail(StoreHostingEmailRequest $request, Domain $domain, ControlPanelManager $panels): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $server = $domain->server;

        if ($server === null || $server->control_panel !== 'cpanel' || ! $server->hasToken() || ! $panels->supports($server))
        {
            return response()->json([
                'success' => false,
                'message' => 'Este hosting no tiene cPanel configurado.',
            ], 422);
        }

        $local = (string) $request->validated('email');
        $mailbox = $local.'@'.strtolower((string) $domain->domain);
        $result = $panels->forServer($server)->createEmailAccount(
            $server,
            $domain,
            $local,
            (string) $request->validated('password'),
        );

        if (! ($result['success'] ?? false))
        {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'No se pudo crear la cuenta de correo.',
            ], 422);
        }

        $this->rememberMailbox($domain, $panels, $mailbox);
        RefreshDomainDataJob::dispatch($domain->id)->afterResponse();

        return response()->json([
            'success' => true,
            'message' => 'Cuenta de correo creada.',
            'email' => $mailbox,
        ]);
    }

    public function ensureSpf(Request $request, Domain $domain, ControlPanelManager $panels): JsonResponse
    {
        $domain = $this->teamDomain($request, $domain);
        $server = $domain->server;

        if ($server === null || $server->control_panel !== 'cpanel' || ! $server->hasToken() || ! $panels->supports($server))
        {
            return response()->json([
                'success' => false,
                'message' => 'Este hosting no tiene cPanel configurado.',
            ], 422);
        }

        $spfRecord = $server->getProvisioningSpfRecord() ?: DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT;
        $result = $panels->forServer($server)->ensureSpfRecord($server, $domain, $spfRecord);

        if (! ($result['success'] ?? false))
        {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'No se pudo agregar el include de SPF.',
            ], 422);
        }

        $record = $this->rememberZoneSpf($domain, $panels);
        RefreshDomainDataJob::dispatch($domain->id)->afterResponse();

        return response()->json([
            'success' => true,
            'message' => 'El include de Revision Alpha quedó en el SPF. El resto de mecanismos se mantiene.',
            'record' => $record,
        ]);
    }

    public function store(StoreApiHostingRequest $request, FlexHostingProvisioner $provisioner, HostingFailureNotice $failures): JsonResponse
    {
        $server = $this->provisioningServer();
        $context = [
            'domain' => (string) $request->validated('domain'),
            'username' => (string) $request->validated('username'),
            'user' => $request->user()?->email,
        ];

        if ($server === null)
        {
            $wanted = trim((string) config('humano_hosting.provision_server', 'odin'));
            $message = $wanted !== ''
                ? 'No hay un servidor '.$wanted.' listo para crear el hosting.'
                : 'No hay un servidor cPanel configurado para el equipo.';
            $failures->report($message, $context);

            return response()->json([
                'success' => false,
                'message' => $failures->userMessage($message),
            ], 422);
        }

        $provider = (string) $request->validated('mail_provider');
        $mxRecords = MailExchange::records($provider, (string) $request->validated('mail_exchange', ''));
        $mailboxes = $mxRecords === [] ? array_values($request->validated('mailboxes')) : [];

        try
        {
            $created = $provisioner->provision(
                $request->user(),
                $server,
                (int) $request->validated('enterprise_id'),
                (string) $request->validated('domain'),
                (string) $request->validated('username'),
                $mailboxes,
                $mxRecords,
            );
        } catch (RuntimeException $exception)
        {
            $technical = $exception->getMessage();
            $failures->report($technical, $context);

            return response()->json([
                'success' => false,
                'message' => $failures->userMessage($technical),
            ], 422);
        }

        $createdMailboxes = $created['mailboxes'];

        foreach ($createdMailboxes as $index => $mailbox)
        {
            if (($mailbox['created'] ?? false) || ! filled($mailbox['error'] ?? null))
            {
                continue;
            }

            $technical = (string) $mailbox['error'];
            $failures->report($technical, $context + ['mailbox' => $mailbox['email'] ?? null]);
            $createdMailboxes[$index]['error'] = $failures->userMessage($technical, 'No se pudo crear el correo. Avisamos al equipo para resolverlo.');
        }

        if (filled($created['mail_dns_error'] ?? null))
        {
            $technical = (string) $created['mail_dns_error'];
            $failures->report($technical, $context);
            $created['mail_dns_error'] = $failures->userMessage($technical, 'El hosting quedó creado, pero no se pudo preparar el correo. Avisamos al equipo para resolverlo.');
        }

        return response()->json([
            'success' => true,
            'plan' => $created['plan'],
            'data' => [
                'id' => $created['domain']->id,
                'domain' => $created['domain']->domain,
                'username' => $created['domain']->username,
                'password' => $created['password'],
                'nameservers' => $created['nameservers'],
                'spf_configured' => $created['spf_configured'],
                'mailboxes' => $createdMailboxes,
                'mail_dns_configured' => $created['mail_dns_configured'],
                'mail_dns_error' => $created['mail_dns_error'],
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function account(Domain $domain, HostingPlanCatalog $plans): array
    {
        $data = HostingAccountPresenter::forDomain(
            $domain,
            (bool) data_get($domain->data, 'auto_suspend', false),
        );
        $data['can_change_plan'] = ! $plans->isFlex($domain->plan);
        $data['plans'] = $data['can_change_plan'] ? $plans->options($domain) : [];

        return $data;
    }

    /**
     * @return list<string>
     */
    private function provisioningNameservers(): array
    {
        $nameservers = $this->provisioningServer()?->getProvisioningNameservers() ?: config('humano_hosting.default_nameservers', []);

        return array_values(array_filter($nameservers, fn (mixed $name) => is_string($name) && $name !== ''));
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
            $name = strtolower(trim($server->name));
            $host = strtolower((string) $server->hostname);

            return $name === $wanted || $host === $wanted || str_starts_with($host, $wanted.'.');
        });
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function passwordResponse(array $result): JsonResponse
    {
        if (! ($result['success'] ?? false))
        {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'No se pudo actualizar la contraseña.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'password' => $result['password'] ?? null,
        ]);
    }

    private function rememberZoneSpf(Domain $domain, ControlPanelManager $panels): ?string
    {
        $server = $domain->server;

        if ($server === null)
        {
            return null;
        }

        $records = $panels->forServer($server)->getSpfRecords($server, $domain);
        $text = is_array($records['records'][0] ?? null) ? (string) ($records['records'][0]['text'] ?? '') : '';

        if ($text === '')
        {
            return null;
        }

        $domain->update([
            'data' => array_merge($domain->data ?? [], [
                'public_spf_check' => [
                    'exists' => true,
                    'has_mailbaby' => DnsHelper::spfIncludesRevisionAlpha($text),
                    'record' => $text,
                ],
            ]),
        ]);

        return $text;
    }

    private function rememberMailbox(Domain $domain, ControlPanelManager $panels, string $mailbox): void
    {
        $server = $domain->server;

        if ($server === null)
        {
            return;
        }

        $emailsResult = $panels->forServer($server)->listEmailAccounts($server, $domain);
        $listedAccounts = ($emailsResult['success'] ?? false) ? ($emailsResult['emails'] ?? []) : [];
        $emailAccounts = collect($listedAccounts !== [] ? $listedAccounts : ($domain->data['email_accounts'] ?? []));

        if (! $emailAccounts->contains(fn ($account): bool => strtolower((string) (is_array($account) ? ($account['email'] ?? '') : '')) === $mailbox))
        {
            $emailAccounts->push([
                'email' => $mailbox,
                'diskused_mb' => 0.0,
                'diskquota_mb' => null,
                'unlimited' => true,
                'usage_percent' => null,
            ]);
        }

        $domain->update([
            'data' => array_merge($domain->data ?? [], [
                'email_accounts' => $emailAccounts
                    ->sortBy(fn ($account): string => strtolower((string) (is_array($account) ? ($account['email'] ?? '') : '')))
                    ->values()
                    ->all(),
            ]),
        ]);
    }

    private function teamDomain(Request $request, Domain $domain): Domain
    {
        $user = $request->user();

        if ($user === null || ! $user->canAccessInfrastructure())
        {
            abort(403);
        }

        $domain->load('server');
        $teamId = $user->currentTeam?->id;

        if ($teamId === null || (int) $domain->server?->team_id !== (int) $teamId)
        {
            abort(404);
        }

        return $domain;
    }

    private function teamAccounts(Request $request): Builder
    {
        $teamId = $request->user()?->currentTeam?->id;

        return Domain::query()
            ->with('server:id,name')
            ->when($teamId, function (Builder $builder) use ($teamId)
            {
                $builder->whereHas('server', function (Builder $server) use ($teamId)
                {
                    $server->where('team_id', $teamId);
                });
            });
    }
}
