<?php

namespace Tests\Feature;

use App\Enums\RegistrationMode;
use App\Models\Subscription;
use App\Models\SubscriptionProduct;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationBillingModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_mode_allows_access_when_current_team_is_configured_demo_team(): void
    {
        config([
            'registration.mode' => 'gate',
            'registration.stripe_product_id' => 'prod_reg_demo_skip',
        ]);

        SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_demo_skip',
            'stripe_price' => 'price_reg_demo_skip',
            'name' => 'Registration',
            'active' => true,
            'category' => 'mailer',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        config(['registration.demo_team_ids' => [(int) $team->id]]);

        $this->actingAs($user)
            ->get('/profile/data')
            ->assertOk();
    }

    public function test_registration_onboarding_qr_page_renders_after_plan_active(): void
    {
        config(['registration.mode' => 'free']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get(route('registration.onboarding.qr'))
            ->assertOk()
            ->assertSee(__('auth.registration.qr_heading'), false);
    }

    public function test_gate_mode_redirects_incomplete_billing_to_registration_billing(): void
    {
        config([
            'registration.mode' => 'gate',
            'registration.stripe_product_id' => 'prod_reg_gate',
        ]);

        SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_gate',
            'stripe_price' => 'price_reg_gate',
            'name' => 'Registration',
            'active' => true,
            'category' => 'mailer',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get('/profile/data')
            ->assertRedirect(route('registration.billing'));
    }

    public function test_checkout_mode_redirects_incomplete_billing_to_checkout_start(): void
    {
        config([
            'registration.mode' => 'checkout',
            'registration.stripe_product_id' => 'prod_reg_checkout',
        ]);

        SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_checkout',
            'stripe_price' => 'price_reg_checkout',
            'name' => 'Registration',
            'active' => true,
            'category' => 'mailer',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get('/profile/data')
            ->assertRedirect(route('registration.checkout.start'));
    }

    public function test_free_mode_does_not_redirect_when_no_active_registration_subscription(): void
    {
        config(['registration.mode' => 'free']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get('/profile/data')
            ->assertOk();
    }

    public function test_gate_mode_allows_access_when_team_has_matching_active_subscription(): void
    {
        config([
            'registration.mode' => 'gate',
            'registration.stripe_product_id' => 'prod_reg_ok',
        ]);

        SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_ok',
            'stripe_price' => 'price_reg_ok',
            'name' => 'Registration',
            'active' => true,
            'category' => 'mailer',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        Subscription::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_reg_ok_test',
            'stripe_status' => 'active',
            'stripe_price' => 'price_reg_ok',
            'quantity' => 1,
        ]);

        $this->actingAs($user)
            ->get('/profile/data')
            ->assertOk();
    }

    public function test_gate_mode_redirects_when_matching_subscription_has_ended(): void
    {
        config([
            'registration.mode' => 'gate',
            'registration.stripe_product_id' => 'prod_reg_ended',
        ]);

        SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_ended',
            'stripe_price' => 'price_reg_ended',
            'name' => 'Registration',
            'active' => true,
            'category' => 'mailer',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        Subscription::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_reg_ended_test',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_reg_ended',
            'quantity' => 1,
            'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get('/profile/data')
            ->assertRedirect(route('registration.billing'));
    }

    public function test_registration_billing_page_requires_gate_mode(): void
    {
        config(['registration.mode' => 'free']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get(route('registration.billing'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_registration_mode_enum_parses_configuration(): void
    {
        config(['registration.mode' => 'GATE']);
        $this->assertSame(RegistrationMode::Gate, RegistrationMode::fromConfiguration());

        config(['registration.mode' => 'unknown']);
        $this->assertSame(RegistrationMode::Free, RegistrationMode::fromConfiguration());
    }

    public function test_billing_info_shows_registration_product_when_price_id_differs_from_db_column(): void
    {
        config([
            'registration.stripe_product_id' => 'prod_reg_sidebar',
            'cashier.secret' => 'sk_test_123',
        ]);

        SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_sidebar',
            'stripe_price' => 'price_stored_on_row',
            'name' => 'Plan Registro Test',
            'active' => true,
            'category' => 'mailer',
            'unit_amount' => 29.99,
            'currency' => 'eur',
            'recurring_interval' => 'month',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get(route('subscription.billing-info', [
                'from_registration' => 1,
                'price_id' => 'price_from_stripe_resolve',
            ]))
            ->assertOk()
            ->assertSee('Plan Registro Test', false);
    }

    public function test_registration_checkout_start_redirects_to_subscription_billing_info(): void
    {
        config([
            'registration.mode' => 'checkout',
            'registration.stripe_product_id' => 'prod_reg_checkout_flow',
            'cashier.secret' => 'sk_test_123',
        ]);

        $product = SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_checkout_flow',
            'stripe_price' => 'price_reg_checkout_flow',
            'name' => 'Registration plan',
            'active' => true,
            'category' => 'mailer',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get(route('registration.checkout.start'))
            ->assertRedirect(route('subscription.billing-info', [
                'from_registration' => 1,
                'product_id' => $product->id,
            ]));
    }

    public function test_gate_mode_allows_shell_poll_routes_while_billing_incomplete(): void
    {
        config([
            'registration.mode' => 'gate',
            'registration.stripe_product_id' => 'prod_reg_shell',
        ]);

        SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_shell',
            'stripe_price' => 'price_reg_shell',
            'name' => 'Registration',
            'active' => true,
            'category' => 'mailer',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get(route('time.running'))
            ->assertOk()
            ->assertJsonFragment(['running' => false]);

        $livewireJsUrl = preg_replace('#/update$#', '/livewire.js', route('default-livewire.update'));

        $this->actingAs($user)
            ->get($livewireJsUrl)
            ->assertSuccessful();
    }

    public function test_gate_mode_passes_when_active_subscription_has_registration_checkout_metadata_even_if_price_not_in_catalog(): void
    {
        config([
            'registration.mode' => 'gate',
            'registration.stripe_product_id' => 'prod_reg_meta',
        ]);

        SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_meta',
            'stripe_price' => 'price_in_catalog_only',
            'name' => 'Registration',
            'active' => true,
            'category' => 'mailer',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        Subscription::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_reg_checkout_meta',
            'stripe_status' => 'active',
            'stripe_price' => 'price_from_stripe_not_listed_in_subscription_products',
            'quantity' => 1,
            'data' => [
                'registration_checkout' => '1',
                'team_id' => (string) $team->id,
            ],
        ]);

        $this->actingAs($user)
            ->get('/profile/data')
            ->assertOk();
    }

    public function test_gate_mode_passes_when_active_subscription_has_payment_link_signup_metadata(): void
    {
        config([
            'registration.mode' => 'gate',
            'registration.stripe_product_id' => 'prod_reg_payment_link',
        ]);

        SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_payment_link',
            'stripe_price' => 'price_reg_payment_link',
            'name' => 'Registration',
            'active' => true,
            'category' => 'mailer',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        Subscription::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_public_pricing',
            'stripe_status' => 'active',
            'stripe_price' => 'price_humano_public_plan_not_registration_catalog',
            'quantity' => 1,
            'data' => [
                'payment_link_signup' => '1',
            ],
        ]);

        $this->actingAs($user)
            ->get('/profile/data')
            ->assertOk();
    }

    public function test_gate_mode_passes_when_registration_catalog_has_no_prices_but_subscription_has_payment_link_signup(): void
    {
        config([
            'registration.mode' => 'gate',
            'registration.stripe_product_id' => 'prod_not_in_subscription_products_table',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->assertTrue(Team::registrationCheckoutStripePriceIds()->isEmpty());

        Subscription::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_no_catalog_row',
            'stripe_status' => 'active',
            'stripe_price' => 'price_public_only',
            'quantity' => 1,
            'data' => [
                'payment_link_signup' => '1',
            ],
        ]);

        $this->actingAs($user)
            ->get('/profile/data')
            ->assertOk();
    }

    public function test_gate_mode_redirects_when_registration_catalog_has_no_prices_and_subscription_has_no_bypass_flags(): void
    {
        config([
            'registration.mode' => 'gate',
            'registration.stripe_product_id' => 'prod_not_in_subscription_products_table',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        Subscription::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_no_bypass',
            'stripe_status' => 'active',
            'stripe_price' => 'price_public_only',
            'quantity' => 1,
            'data' => [],
        ]);

        $this->actingAs($user)
            ->get('/profile/data')
            ->assertRedirect(route('registration.billing'));
    }

    public function test_gate_mode_redirects_to_pricing_when_after_public_payment_link_flag_is_set(): void
    {
        config([
            'registration.mode' => 'gate',
            'registration.stripe_product_id' => 'prod_reg_gate_pricing_redirect',
        ]);

        SubscriptionProduct::create([
            'stripe_product' => 'prod_reg_gate_pricing_redirect',
            'stripe_price' => 'price_reg_gate_pricing_redirect',
            'name' => 'Registration',
            'active' => true,
            'category' => 'mailer',
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        Subscription::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_gate_pricing_redirect',
            'stripe_status' => 'active',
            'stripe_price' => 'price_wrong_no_match',
            'quantity' => 1,
            'data' => [],
        ]);

        $this->actingAs($user)
            ->withSession(['humano_after_public_payment_link_checkout' => true])
            ->get('/profile/data')
            ->assertRedirect(route('pricing'))
            ->assertSessionHas('error');
    }

    public function test_billing_info_does_not_error_when_subscription_product_category_is_null(): void
    {
        config([
            'registration.mode' => 'free',
            'cashier.secret' => 'sk_test_123',
        ]);

        $product = SubscriptionProduct::create([
            'stripe_product' => 'prod_null_category_row',
            'stripe_price' => 'price_null_category_row',
            'name' => 'Plan sin categoría',
            'active' => true,
            'category' => null,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get(route('subscription.billing-info', [
                'from_registration' => 1,
                'product_id' => $product->id,
            ]))
            ->assertOk();
    }
}
