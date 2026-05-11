<?php

namespace Tests\Feature;

use App\Contracts\CheckoutSessionRetriever;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamCheckoutSessionSubscriptionSyncer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
            ->assertRedirect(route('subscription.index'))
            ->assertSessionHas('success');

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
            ->assertRedirect(route('subscription.index'))
            ->assertSessionHas('success');

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
            ->assertRedirect(route('subscription.index'));

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
            ->assertRedirect(route('subscription.index'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->current_team_id);
        $this->assertTrue($user->ownedTeams()->where('personal_team', true)->exists());
        $this->assertAuthenticatedAs($user);
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
