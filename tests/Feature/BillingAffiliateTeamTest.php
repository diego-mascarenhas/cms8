<?php

namespace Tests\Feature;

use App\Mail\AffiliatePurchaseInvitationMail;
use App\Models\AffiliateInvitation;
use App\Models\BillingAffiliateCommission;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\AffiliateCommissionRecorder;
use App\Services\AffiliateReferralLinkBuilder;
use App\Services\PaymentLinkAffiliateTeamAttributionService;
use App\Services\TeamStripeCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingAffiliateTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['humano_pricing.affiliate_commission_percent' => 30]);
    }

    public function test_commission_recorded_when_paying_team_has_referrer_stripe_id(): void
    {
        $referrerOwner = User::factory()->create();
        $referrerTeam = Team::factory()->create([
            'user_id' => $referrerOwner->id,
            'stripe_id' => 'cus_referrer_abc',
        ]);
        $referrerOwner->forceFill(['current_team_id' => $referrerTeam->id])->save();

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_paying_xyz',
            'referred_by' => 'cus_referrer_abc',
        ]);
        $payingOwner->forceFill(['current_team_id' => $payingTeam->id])->save();

        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'hunter',
            'stripe_id' => 'sub_referred_ads',
            'stripe_status' => 'active',
            'stripe_price' => 'price_hunter',
            'quantity' => 1,
            'referred_by' => 'cus_referrer_abc',
            'affiliate_commission_percent' => 30,
        ]);

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($payingTeam, [
            'id' => 'in_test_aff_team_1',
            'customer' => 'cus_paying_xyz',
            'subscription' => 'sub_referred_ads',
            'amount_paid' => 10000,
            'currency' => 'eur',
        ]);

        $this->assertDatabaseHas('billing_affiliate_commissions', [
            'stripe_invoice_id' => 'in_test_aff_team_1',
            'paying_team_id' => $payingTeam->id,
            'referrer_team_id' => $referrerTeam->id,
            'commission_amount_cents' => 3000,
        ]);
    }

    public function test_no_commission_when_referrer_is_same_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => 'cus_same_team',
            'referred_by' => 'cus_same_team',
        ]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        $team->subscriptions()->create([
            'user_id' => $owner->id,
            'type' => 'hunter',
            'stripe_id' => 'sub_same_team',
            'stripe_status' => 'active',
            'stripe_price' => 'price_hunter',
            'quantity' => 1,
            'referred_by' => 'cus_same_team',
            'affiliate_commission_percent' => 30,
        ]);

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($team, [
            'id' => 'in_test_same_team',
            'customer' => 'cus_same_team',
            'subscription' => 'sub_same_team',
            'amount_paid' => 5000,
            'currency' => 'usd',
        ]);

        $this->assertSame(0, BillingAffiliateCommission::query()->count());
    }

    public function test_no_commission_without_referred_by(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => 'cus_no_ref',
            'referred_by' => null,
        ]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($team, [
            'id' => 'in_test_no_ref',
            'customer' => 'cus_no_ref',
            'amount_paid' => 5000,
            'currency' => 'eur',
        ]);

        $this->assertSame(0, BillingAffiliateCommission::query()->count());
    }

    public function test_no_commission_on_unreferred_product_subscription(): void
    {
        $referrerOwner = User::factory()->create();
        $referrerTeam = Team::factory()->create([
            'user_id' => $referrerOwner->id,
            'stripe_id' => 'cus_referrer_ads_only',
        ]);

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_paying_multi',
            'referred_by' => 'cus_referrer_ads_only',
        ]);

        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'hunter',
            'stripe_id' => 'sub_ads_referred',
            'stripe_status' => 'active',
            'stripe_price' => 'price_hunter',
            'quantity' => 1,
            'referred_by' => 'cus_referrer_ads_only',
            'affiliate_commission_percent' => 30,
        ]);

        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_mailer_own',
            'stripe_status' => 'active',
            'stripe_price' => 'price_mailer',
            'quantity' => 1,
            'referred_by' => null,
            'affiliate_commission_percent' => null,
        ]);

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($payingTeam, [
            'id' => 'in_mailer_own',
            'customer' => 'cus_paying_multi',
            'subscription' => 'sub_mailer_own',
            'amount_paid' => 8000,
            'currency' => 'eur',
        ]);

        $this->assertSame(0, BillingAffiliateCommission::query()->count());

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($payingTeam, [
            'id' => 'in_ads_referred',
            'customer' => 'cus_paying_multi',
            'subscription' => 'sub_ads_referred',
            'amount_paid' => 10000,
            'currency' => 'eur',
        ]);

        $this->assertDatabaseHas('billing_affiliate_commissions', [
            'stripe_invoice_id' => 'in_ads_referred',
            'referrer_team_id' => $referrerTeam->id,
            'commission_amount_cents' => 3000,
        ]);
    }

    public function test_attribution_stamps_referrer_on_subscription(): void
    {
        config(['humano_pricing.affiliate_commission_percent' => 25]);

        $referrerOwner = User::factory()->create();
        Team::factory()->create([
            'user_id' => $referrerOwner->id,
            'stripe_id' => 'cus_sub_stamp_ref',
        ]);

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_sub_stamp_pay',
        ]);

        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'hunter',
            'stripe_id' => 'sub_stamp_ads',
            'stripe_status' => 'active',
            'stripe_price' => 'price_hunter',
            'quantity' => 1,
        ]);

        $session = \Stripe\Checkout\Session::constructFrom([
            'id' => 'cs_stamp_sub',
            'object' => 'checkout.session',
            'subscription' => 'sub_stamp_ads',
            'client_reference_id' => 'cus_sub_stamp_ref',
        ]);

        app(PaymentLinkAffiliateTeamAttributionService::class)
            ->syncTeamReferrerFromSession($payingTeam, $session);

        $subscription = $payingTeam->subscriptions()->where('stripe_id', 'sub_stamp_ads')->first();

        $this->assertSame('cus_sub_stamp_ref', $payingTeam->fresh()->referred_by);
        $this->assertSame('cus_sub_stamp_ref', $subscription?->referred_by);
        $this->assertSame(25.0, (float) $subscription?->affiliate_commission_percent);
    }

    public function test_referral_link_builder_appends_client_reference_id(): void
    {
        $builder = app(AffiliateReferralLinkBuilder::class);
        $url = $builder->buildLink(
            'https://buy.stripe.com/testlink',
            'cus_ref_123',
            'buyer@example.com',
        );

        $this->assertStringContainsString('client_reference_id=cus_ref_123', $url);
        $this->assertStringContainsString('prefilled_email=buyer%40example.com', $url);
    }

    public function test_affiliate_invite_sends_email(): void
    {
        Mail::fake();

        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'checkout_url' => 'https://buy.stripe.com/test_assistant',
                    'checkout_available' => true,
                ],
            ],
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_invite_ref'])->save();
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->grantBillingAccess($user);

        $this->actingAs($user)->post(route('billing.affiliate-invite'), [
            'invite_name' => 'Jane Doe',
            'invite_email' => 'jane@example.com',
            'invite_plan' => 'assistant',
        ])->assertRedirect(route('billing.index'));

        Mail::assertSent(AffiliatePurchaseInvitationMail::class, function ($mail): bool
        {
            return $mail->hasTo('jane@example.com')
                && $mail->inviteeName === 'Jane Doe'
                && str_contains($mail->checkoutUrl, 'affiliate/capture')
                && str_contains($mail->checkoutUrl, 'ref=cus_invite_ref')
                && str_contains($mail->checkoutUrl, 'client_reference_id%3Dcus_invite_ref')
                && str_contains($mail->pricingUrl, '/pricing');
        });

        $this->assertDatabaseHas('affiliate_invitations', [
            'team_id' => $team->id,
            'invitee_email' => 'jane@example.com',
            'plan_id' => 'assistant',
        ]);

        $invitation = \App\Models\AffiliateInvitation::query()
            ->where('invitee_email', 'jane@example.com')
            ->first();
        $this->assertNotNull($invitation?->tracking_token);
        $this->assertNotNull($invitation?->sent_at);
    }

    public function test_affiliate_invite_email_uses_spain_spanish(): void
    {
        Mail::fake();
        app()->setLocale('es_AR');

        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'name' => 'Assistant',
                    'checkout_url' => 'https://buy.stripe.com/test_assistant',
                    'checkout_available' => true,
                ],
            ],
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create(['name' => 'Carlos Referidor']);
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_invite_ref'])->save();
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->grantBillingAccess($user);

        $this->actingAs($user)->post(route('billing.affiliate-invite'), [
            'invite_name' => 'Jane Doe',
            'invite_email' => 'jane@example.com',
            'invite_plan' => 'assistant',
        ])->assertRedirect(route('billing.index'));

        $mailable = Mail::sent(AffiliatePurchaseInvitationMail::class)->first();
        $this->assertNotNull($mailable);

        $html = $mailable->render();

        $this->assertStringContainsString('Puedes ignorarlo', $html);
        $this->assertStringNotContainsString('Podés', $html);
        $this->assertStringContainsString('Ver todos los planes', $html);
        $this->assertStringContainsString('Suscribirme a Assistant', $html);
        $this->assertStringContainsString('Hola, Jane Doe', $html);
    }

    public function test_affiliate_invite_returns_field_validation_errors(): void
    {
        Mail::fake();

        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'checkout_url' => 'https://buy.stripe.com/test_assistant',
                    'checkout_available' => true,
                ],
            ],
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_invite_ref'])->save();
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->grantBillingAccess($user);

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.affiliate-invite'), [])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHasErrors(['invite_name', 'invite_email', 'invite_plan']);

        $this->get(route('billing.index'))
            ->assertOk()
            ->assertSee('affiliate-invite-form', false)
            ->assertDontSee('var myModal = new bootstrap.Modal(document.getElementById(\'editBillingModal\'))', false);

        Mail::assertNothingSent();
    }

    public function test_affiliate_invite_uses_team_from_when_configured(): void
    {
        Mail::fake();

        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'checkout_url' => 'https://buy.stripe.com/test_assistant',
                    'checkout_available' => true,
                ],
            ],
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create(['name' => 'Carlos Referidor']);
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_from_test'])->save();
        $team->setSetting('mail_from_name', 'Mi Empresa SA', ['group' => 'email', 'type' => 'string']);
        $team->setSetting('mail_from_address', 'referidos@miempresa.test', ['group' => 'email', 'type' => 'string']);
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->grantBillingAccess($user);

        $this->actingAs($user)->post(route('billing.affiliate-invite'), [
            'invite_name' => 'Ana',
            'invite_email' => 'ana@example.com',
            'invite_plan' => 'assistant',
        ])->assertRedirect(route('billing.index'));

        Mail::assertSent(AffiliatePurchaseInvitationMail::class, 1);

        $mailable = Mail::sent(AffiliatePurchaseInvitationMail::class)->first();
        $this->assertNotNull($mailable);
        $mailable->build();

        $this->assertCount(1, $mailable->from);
        $this->assertSame('referidos@miempresa.test', $mailable->from[0]['address']);
        $this->assertSame('Mi Empresa SA', $mailable->from[0]['name']);
    }

    public function test_billing_index_lists_sent_invitations(): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->grantBillingAccess($user);

        AffiliateInvitation::query()->create([
            'team_id' => $team->id,
            'invited_by_user_id' => $user->id,
            'invitee_name' => 'Pedro Test',
            'invitee_email' => 'pedro@example.com',
            'plan_id' => 'assistant',
            'plan_name' => 'Assistant',
        ]);

        $this->actingAs($user)->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Invitaciones enviadas', false)
            ->assertSee('Pedro Test', false)
            ->assertSee('pedro@example.com', false)
            ->assertSee('Assistant', false);
    }

    public function test_billing_affiliate_shows_stripe_setup_alert_when_no_stripe_id(): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => null])->save();
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->grantBillingAccess($user);

        $this->mock(TeamStripeCustomerService::class, function ($mock): void
        {
            $mock->shouldReceive('getOrCreateStripeCustomerIdForCategory')
                ->andReturn(null);
        });

        $this->actingAs($user)->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Activa tu código de referido', false)
            ->assertSee('Activar en Stripe', false)
            ->assertDontSee('Invitar por email', false)
            ->assertDontSee('affiliate-invite-form', false);
    }

    public function test_billing_affiliate_setup_stripe_persists_customer_id(): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => null])->save();
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->grantBillingAccess($user);

        $this->mock(TeamStripeCustomerService::class, function ($mock) use ($team): void
        {
            $mock->shouldReceive('getOrCreateStripeCustomerIdForCategory')
                ->with(\Mockery::on(fn ($passedTeam): bool => $passedTeam->id === $team->id), 'mailer')
                ->andReturnUsing(function ($passedTeam): string
                {
                    $passedTeam->forceFill(['stripe_id' => 'cus_affiliate_setup'])->save();

                    return 'cus_affiliate_setup';
                });
        });

        $this->actingAs($user)->post(route('billing.affiliate-setup-stripe'))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('success');

        $this->assertSame('cus_affiliate_setup', $team->fresh()->stripe_id);
    }

    public function test_billing_affiliate_auto_registers_stripe_customer_on_index(): void
    {
        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'name' => 'Assistant',
                    'checkout_url' => 'https://buy.stripe.com/test_assistant',
                    'checkout_available' => true,
                ],
            ],
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => null])->save();
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->grantBillingAccess($user);

        $this->mock(TeamStripeCustomerService::class, function ($mock) use ($team): void
        {
            $mock->shouldReceive('getOrCreateStripeCustomerIdForCategory')
                ->once()
                ->with(\Mockery::on(fn ($passedTeam): bool => $passedTeam->id === $team->id), 'mailer')
                ->andReturnUsing(function ($passedTeam): string
                {
                    $passedTeam->forceFill(['stripe_id' => 'cus_auto_index'])->save();

                    return 'cus_auto_index';
                });
        });

        $this->actingAs($user)->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Invitar por email', false)
            ->assertSee('affiliate-invite-form', false)
            ->assertDontSee('Activa tu código de referido', false);

        $this->assertSame('cus_auto_index', $team->fresh()->stripe_id);
    }

    /**
     * Billing routes are gated on admin/root ({@see \App\Models\User::canAccessBilling}); the
     * affiliates module alone does not open them.
     */
    private function grantBillingAccess(User $user): void
    {
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $user->refresh();
    }
}
