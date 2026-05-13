<?php

namespace Tests\Feature;

use App\Contracts\CheckoutSessionRetriever;
use App\Models\Enterprise;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamCheckoutSessionSubscriptionSyncer;
use App\Support\HumanoPublicPaymentLinkCheckout;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Tests\TestCase;

class PaymentLinkCheckoutCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([Registered::class]);
        $this->bindNoopTeamCheckoutSessionSubscriptionSyncer();
    }

    public function test_register_first_mode_redirects_to_register(): void
    {
        config(['humano_pricing.signup_completion' => 'register_first']);

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_123',
            'category' => 'assistant',
        ]))
            ->assertRedirect(route('register'))
            ->assertSessionHas('info');
    }

    public function test_requires_session_id(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $this->get(route('pricing.checkout.complete', ['category' => 'assistant']))
            ->assertRedirect(route('pricing'))
            ->assertSessionHasErrors('session_id');
    }

    public function test_rejects_invalid_category_query(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_123',
            'category' => 'mailer',
        ]))
            ->assertRedirect(route('pricing'))
            ->assertSessionHasErrors('category');
    }

    public function test_accepts_foundation_category_query(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $email = 'payment-link-foundation-'.uniqid('', true).'@example.com';

        $session = Session::constructFrom([
            'id' => 'cs_test_foundation',
            'object' => 'checkout.session',
            'status' => 'complete',
            'mode' => 'subscription',
            'payment_status' => 'paid',
            'customer' => 'cus_test_foundation',
            'subscription' => 'sub_test_foundation',
            'customer_details' => [
                'email' => $email,
                'name' => 'Foundation Buyer',
            ],
        ]);

        $this->instance(CheckoutSessionRetriever::class, new class($session) implements CheckoutSessionRetriever
        {
            public function __construct(private Session $session) {}

            public function retrieve(string $sessionId, string $category): ?Session
            {
                return $this->session;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_foundation',
            'category' => 'foundation',
        ]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $this->assertAuthenticatedAs(User::where('email', $email)->first());
    }

    public function test_redirects_to_pricing_when_stripe_session_missing(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $this->instance(CheckoutSessionRetriever::class, new class implements CheckoutSessionRetriever
        {
            public function retrieve(string $sessionId, string $category): ?Session
            {
                return null;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_missing',
            'category' => 'business',
        ]))
            ->assertRedirect(route('pricing'))
            ->assertSessionHas('error');
    }

    public function test_redirects_when_checkout_not_paid(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $session = Session::constructFrom([
            'id' => 'cs_test_open',
            'object' => 'checkout.session',
            'status' => 'open',
            'mode' => 'subscription',
            'payment_status' => 'unpaid',
            'customer' => 'cus_test',
            'subscription' => 'sub_test',
            'customer_details' => [
                'email' => 'open-session@example.com',
                'name' => 'Open',
            ],
        ]);

        $this->instance(CheckoutSessionRetriever::class, new class($session) implements CheckoutSessionRetriever
        {
            public function __construct(private Session $session) {}

            public function retrieve(string $sessionId, string $category): ?Session
            {
                return $this->session;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_open',
            'category' => 'assistant',
        ]))
            ->assertRedirect(route('pricing'))
            ->assertSessionHas('error');
    }

    public function test_accepts_checkout_when_category_query_omitted(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $email = 'payment-link-no-cat-'.uniqid('', true).'@example.com';

        $session = Session::constructFrom([
            'id' => 'cs_test_no_cat',
            'object' => 'checkout.session',
            'status' => 'complete',
            'mode' => 'subscription',
            'payment_status' => 'paid',
            'customer' => 'cus_test_no_cat',
            'subscription' => 'sub_test_no_cat',
            'customer_details' => [
                'email' => $email,
                'name' => 'No Category',
            ],
        ]);

        $this->instance(CheckoutSessionRetriever::class, new class($session) implements CheckoutSessionRetriever
        {
            public function __construct(private Session $session) {}

            public function retrieve(string $sessionId, string $category): ?Session
            {
                return $this->session;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_no_cat',
        ]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success')
            ->assertSessionHas(HumanoPublicPaymentLinkCheckout::SESSION_SHOW_DASHBOARD_WHATSAPP_QR_CTA, true);

        $this->assertAuthenticatedAs(User::where('email', $email)->first());
    }

    public function test_creates_user_logs_in_and_syncs_subscription_when_payment_complete(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $email = 'payment-link-buyer-'.uniqid('', true).'@example.com';

        $session = Session::constructFrom([
            'id' => 'cs_test_paid',
            'object' => 'checkout.session',
            'status' => 'complete',
            'mode' => 'subscription',
            'payment_status' => 'paid',
            'customer' => 'cus_test_paid_123',
            'subscription' => 'sub_test_paid_123',
            'customer_details' => [
                'email' => $email,
                'name' => 'Payment Link Buyer',
            ],
        ]);

        $this->instance(CheckoutSessionRetriever::class, new class($session) implements CheckoutSessionRetriever
        {
            public function __construct(private Session $session) {}

            public function retrieve(string $sessionId, string $category): ?Session
            {
                return $this->session;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_paid',
            'category' => 'assistant',
        ]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success')
            ->assertSessionHas(HumanoPublicPaymentLinkCheckout::SESSION_SHOW_DASHBOARD_WHATSAPP_QR_CTA, true);

        $this->assertDatabaseHas('users', ['email' => $email]);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_user_with_matching_customer_is_logged_in(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $user = User::factory()->withPersonalTeam()->create([
            'email' => 'existing-pl-'.uniqid('', true).'@example.com',
        ]);
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->forceFill(['stripe_id' => 'cus_existing_pl'])->save();

        $session = Session::constructFrom([
            'id' => 'cs_test_existing',
            'object' => 'checkout.session',
            'status' => 'complete',
            'mode' => 'subscription',
            'payment_status' => 'paid',
            'customer' => 'cus_existing_pl',
            'subscription' => 'sub_test_existing',
            'customer_details' => [
                'email' => $user->email,
                'name' => $user->name,
            ],
        ]);

        $this->instance(CheckoutSessionRetriever::class, new class($session) implements CheckoutSessionRetriever
        {
            public function __construct(private Session $session) {}

            public function retrieve(string $sessionId, string $category): ?Session
            {
                return $this->session;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_existing',
            'category' => 'business',
        ]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(HumanoPublicPaymentLinkCheckout::SESSION_SHOW_DASHBOARD_WHATSAPP_QR_CTA, true);

        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_user_without_team_gets_personal_team_and_login(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $email = 'orphan-pl-'.uniqid('', true).'@example.com';
        $user = User::factory()->create([
            'email' => $email,
        ]);
        $this->assertSame(0, $user->ownedTeams()->count());
        $this->assertNull($user->current_team_id);

        $customerId = 'cus_orphan_pl_'.uniqid('', true);
        $session = Session::constructFrom([
            'id' => 'cs_test_orphan',
            'object' => 'checkout.session',
            'status' => 'complete',
            'mode' => 'subscription',
            'payment_status' => 'paid',
            'customer' => $customerId,
            'subscription' => 'sub_test_orphan',
            'customer_details' => [
                'email' => $email,
                'name' => 'Orphan Buyer',
            ],
        ]);

        $this->instance(CheckoutSessionRetriever::class, new class($session) implements CheckoutSessionRetriever
        {
            public function __construct(private Session $session) {}

            public function retrieve(string $sessionId, string $category): ?Session
            {
                return $this->session;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_orphan',
            'category' => 'business',
        ]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success')
            ->assertSessionHas(HumanoPublicPaymentLinkCheckout::SESSION_SHOW_DASHBOARD_WHATSAPP_QR_CTA, true);

        $user->refresh();
        $this->assertNotNull($user->current_team_id);
        $this->assertTrue($user->ownedTeams()->where('personal_team', true)->exists());
        $this->assertAuthenticatedAs($user);
    }

    public function test_new_user_prefers_stripe_individual_name_when_company_name_also_on_session(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $email = 'b2b-buyer-'.uniqid('', true).'@example.com';

        $session = Session::constructFrom([
            'id' => 'cs_test_b2b_names',
            'object' => 'checkout.session',
            'status' => 'complete',
            'mode' => 'subscription',
            'payment_status' => 'paid',
            'customer' => 'cus_test_b2b_names',
            'subscription' => 'sub_test_b2b_names',
            'customer_details' => [
                'email' => $email,
                'name' => 'Acme Corporation SL',
                'business_name' => 'Acme Corporation SL',
                'individual_name' => 'María García López',
            ],
        ]);

        $this->instance(CheckoutSessionRetriever::class, new class($session) implements CheckoutSessionRetriever
        {
            public function __construct(private Session $session) {}

            public function retrieve(string $sessionId, string $category): ?Session
            {
                return $this->session;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_b2b_names',
            'category' => 'assistant',
        ]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('María García López', $user->name);
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $this->assertSame("María's Team", $team->name);
    }

    public function test_new_user_when_name_matches_business_only_does_not_use_company_as_person_name(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $suffix = uniqid('', true);
        $email = "first.last.{$suffix}@example.com";
        $expectedDisplayName = Str::title(str_replace(['.', '_', '-'], ' ', Str::before($email, '@')));

        $session = Session::constructFrom([
            'id' => 'cs_test_company_only_'.$suffix,
            'object' => 'checkout.session',
            'status' => 'complete',
            'mode' => 'subscription',
            'payment_status' => 'paid',
            'customer' => 'cus_test_company_only',
            'subscription' => 'sub_test_company_only',
            'customer_details' => [
                'email' => $email,
                'name' => 'Widgets International SA',
                'business_name' => 'Widgets International SA',
            ],
        ]);

        $this->instance(CheckoutSessionRetriever::class, new class($session) implements CheckoutSessionRetriever
        {
            public function __construct(private Session $session) {}

            public function retrieve(string $sessionId, string $category): ?Session
            {
                return $this->session;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_company_only_'.$suffix,
            'category' => 'assistant',
        ]))
            ->assertRedirect(route('dashboard'));

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame($expectedDisplayName, $user->name);
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $this->assertSame(explode(' ', $expectedDisplayName, 2)[0]."'s Team", $team->name);
    }

    public function test_payment_link_sets_billing_enterprise_referred_by_from_numeric_custom_field(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        $referrerOwner = User::factory()->create();
        $referrerTeam = Team::factory()->create(['user_id' => $referrerOwner->id]);
        $referrerOwner->forceFill(['current_team_id' => $referrerTeam->id])->save();

        $referrerEnterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($referrerTeam->id)->create([
            'type_id' => 1,
            'code' => 'REF-PL-'.uniqid('', true),
            'referred_by' => null,
            'payment_type_id' => null,
            'invoice_type_id' => null,
            'status_id' => 1,
        ]));

        $email = 'pl-affiliate-'.uniqid('', true).'@example.com';
        $customerId = 'cus_pl_affiliate_'.uniqid('', true);

        $session = Session::constructFrom([
            'id' => 'cs_test_pl_affiliate',
            'object' => 'checkout.session',
            'status' => 'complete',
            'mode' => 'subscription',
            'payment_status' => 'paid',
            'customer' => $customerId,
            'subscription' => 'sub_test_pl_affiliate',
            'customer_details' => [
                'email' => $email,
                'name' => 'Affiliate Buyer',
            ],
            'custom_fields' => [
                [
                    'key' => 'referente',
                    'type' => 'numeric',
                    'numeric' => [
                        'value' => (string) $referrerEnterprise->id,
                    ],
                ],
            ],
        ]);

        $this->instance(CheckoutSessionRetriever::class, new class($session) implements CheckoutSessionRetriever
        {
            public function __construct(private Session $session) {}

            public function retrieve(string $sessionId, string $category): ?Session
            {
                return $this->session;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_pl_affiliate',
            'category' => 'assistant',
        ]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $buyer = User::where('email', $email)->first();
        $this->assertNotNull($buyer);
        $payingTeam = $buyer->currentTeam;
        $this->assertNotNull($payingTeam);

        $billingEnterprise = Enterprise::withoutGlobalScopes()
            ->where('team_id', $payingTeam->id)
            ->where('type_id', 1)
            ->where('code', $customerId)
            ->first();

        $this->assertNotNull($billingEnterprise);
        $this->assertSame((string) $referrerEnterprise->id, $billingEnterprise->referred_by);
    }

    public function test_payment_link_does_not_set_referred_by_when_referrer_is_same_team(): void
    {
        config(['humano_pricing.signup_completion' => 'payment_link']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);

        $email = 'pl-same-team-'.uniqid('', true).'@example.com';
        $user = User::factory()->withPersonalTeam()->create(['email' => $email]);
        $team = $user->currentTeam;
        $this->assertNotNull($team);
        $team->forceFill(['stripe_id' => null])->save();

        $sameTeamEnterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'type_id' => 1,
            'code' => 'REF-SAME-'.uniqid('', true),
            'referred_by' => null,
            'payment_type_id' => null,
            'invoice_type_id' => null,
            'status_id' => 1,
        ]));

        $customerId = 'cus_pl_same_'.uniqid('', true);

        $session = Session::constructFrom([
            'id' => 'cs_test_pl_same_team',
            'object' => 'checkout.session',
            'status' => 'complete',
            'mode' => 'subscription',
            'payment_status' => 'paid',
            'customer' => $customerId,
            'subscription' => 'sub_test_pl_same',
            'customer_details' => [
                'email' => $email,
                'name' => 'Same Team Buyer',
            ],
            'custom_fields' => [
                [
                    'key' => 'referente',
                    'type' => 'numeric',
                    'numeric' => [
                        'value' => (string) $sameTeamEnterprise->id,
                    ],
                ],
            ],
        ]);

        $this->instance(CheckoutSessionRetriever::class, new class($session) implements CheckoutSessionRetriever
        {
            public function __construct(private Session $session) {}

            public function retrieve(string $sessionId, string $category): ?Session
            {
                return $this->session;
            }
        });

        $this->get(route('pricing.checkout.complete', [
            'session_id' => 'cs_test_pl_same_team',
            'category' => 'assistant',
        ]))
            ->assertRedirect(route('dashboard'));

        $team->refresh();

        $billingEnterprise = Enterprise::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('type_id', 1)
            ->where('code', $customerId)
            ->first();

        if ($billingEnterprise !== null)
        {
            $this->assertNull($billingEnterprise->referred_by);
        }
    }

    private function bindNoopTeamCheckoutSessionSubscriptionSyncer(): void
    {
        $this->app->bind(TeamCheckoutSessionSubscriptionSyncer::class, function (): TeamCheckoutSessionSubscriptionSyncer
        {
            return new class extends TeamCheckoutSessionSubscriptionSyncer
            {
                #[\Override]
                public function sync(Team $team, Session $session, string $category, int $actingUserId, bool $fromPublicPaymentLinkCheckout = false): void {}
            };
        });
    }
}
