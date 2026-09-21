<?php

namespace Tests\Feature\Api;

use App\Mail\AffiliatePurchaseInvitationMail;
use App\Models\AffiliateInvitation;
use App\Models\BillingAffiliateCommission;
use App\Models\Module;
use App\Models\ServiceSync;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AffiliateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        config(['humano_pricing.affiliate_commission_percent' => 30]);
    }

    /**
     * @return array{0: User, 1: Team, 2: string}
     */
    private function adminWithAffiliatesModule(?array $teamAttrs = null): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        if ($teamAttrs !== null)
        {
            $team->forceFill($teamAttrs)->save();
        }

        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Affiliate program',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $team->enableModule('affiliates');

        $token = $user->createToken('idoneo-affiliates-test')->plainTextToken;

        return [$user->fresh(), $team->fresh(), $token];
    }

    public function test_dashboard_returns_affiliate_payload_for_eligible_team(): void
    {
        [$user, $team, $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_api_referrer',
            'referred_by' => null,
        ]);

        $payingOwner = User::factory()->create([
            'email' => 'ana.referida@example.com',
        ]);
        $payingTeam = Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_api_paying',
            'referred_by' => 'cus_api_referrer',
        ]);

        AffiliateInvitation::query()->create([
            'team_id' => $team->id,
            'invited_by_user_id' => $user->id,
            'invitee_name' => 'Ana Referida',
            'invitee_email' => 'ana.referida@example.com',
            'plan_id' => 'hunter',
            'plan_name' => 'Hunter',
            'tracking_token' => AffiliateInvitation::generateTrackingToken(),
            'sent_at' => now()->subDay(),
            'opened_at' => now()->subHours(4),
        ]);

        BillingAffiliateCommission::query()->create([
            'paying_team_id' => $payingTeam->id,
            'referrer_team_id' => $team->id,
            'stripe_invoice_id' => 'in_api_aff_1',
            'amount_paid_cents' => 10000,
            'currency' => 'eur',
            'commission_percent' => 30,
            'commission_amount_cents' => 3000,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.referral_code', 'cus_api_referrer')
            ->assertJsonPath('data.commission_percent', 30)
            ->assertJsonPath('data.totals_as_referrer.EUR.commission_cents', 3000)
            ->assertJsonPath('data.referrals.0.name', 'Ana Referida')
            ->assertJsonPath('data.referrals.0.email', 'ana.referida@example.com')
            ->assertJsonPath('data.referrals.0.contracted', true)
            ->assertJsonPath('data.referrals.0.commission_cents', 3000)
            ->assertJsonPath('data.referrals.0.plan_name', 'Hunter')
            ->assertJsonPath('data.referrals.0.status', 'Contrató');

        $this->assertNotNull($response->json('data.referrals.0.opened_at'));
        $this->assertNotEmpty($response->json('data.commissions_as_referrer'));
    }

    public function test_dashboard_includes_referred_subscription_services(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_service_referrer',
            'referred_by' => null,
        ]);

        $payingOwner = User::factory()->create([
            'email' => 'repuestos@example.com',
        ]);
        $payingTeam = Team::factory()->create([
            'name' => 'Respuestos AV',
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_service_paying',
            'referred_by' => 'cus_service_referrer',
        ]);
        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_service_assistant',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
            'referred_by' => 'cus_service_referrer',
            'data' => ['current_period_end' => '2026-11-01T00:00:00+00:00'],
        ]);
        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'hosting',
            'stripe_id' => 'sub_service_hosting',
            'stripe_status' => 'active',
            'stripe_price' => 'price_hosting_monthly',
            'quantity' => 1,
            'referred_by' => 'cus_service_referrer',
            'data' => ['current_period_end' => '2026-10-15T00:00:00+00:00'],
        ]);
        ServiceSync::query()->create([
            'stripe_id' => 'sub_service_hosting',
            'provider' => 'stripe',
            'type' => 'sell',
            'team_id' => $payingTeam->id,
            'status' => 'active',
            'amount_total' => 21.99,
            'price_currency' => 'EUR',
            'current_period_end' => '2026-10-15T00:00:00+00:00',
        ]);
        ServiceSync::query()->create([
            'stripe_id' => 'sub_service_assistant',
            'provider' => 'stripe',
            'type' => 'sell',
            'team_id' => $payingTeam->id,
            'status' => 'active',
            'amount_total' => 49,
            'price_currency' => 'EUR',
            'current_period_end' => '2026-11-01T00:00:00+00:00',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.referrals'));
        $response->assertJsonPath('data.referrals.0.name', 'Respuestos AV')
            ->assertJsonPath('data.referrals.0.plan_name', 'Hosting')
            ->assertJsonPath('data.referrals.0.commission_cents', 660)
            ->assertJsonPath('data.referrals.0.currency', 'EUR')
            ->assertJsonPath('data.referrals.1.plan_name', 'Assistant')
            ->assertJsonPath('data.referrals.1.commission_cents', 1470)
            ->assertJsonPath('data.referrals.1.currency', 'EUR')
            ->assertJsonPath('data.totals_as_referrer.EUR.commission_cents', 2130);
        $this->assertStringStartsWith('2026-10-15', (string) $response->json('data.referrals.0.renews_at'));
        $this->assertStringStartsWith('2026-11-01', (string) $response->json('data.referrals.1.renews_at'));
    }

    public function test_dashboard_marks_referred_team_as_ineligible(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_referred_team',
            'referred_by' => 'cus_someone_else',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.referral_code', null);
    }

    public function test_dashboard_available_without_affiliates_module(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $token = $user->createToken('no-module')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.eligible', true);
    }

    public function test_dashboard_filters_plans_by_product_catalog(): void
    {
        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'catalog' => 'assistant',
                    'name' => 'Assistant',
                    'checkout_url' => 'https://buy.stripe.com/test_assistant',
                    'checkout_available' => true,
                    'public' => true,
                ],
                [
                    'id' => 'hunter',
                    'catalog' => 'platform',
                    'name' => 'Hunter',
                    'checkout_url' => 'https://buy.stripe.com/test_hunter',
                    'checkout_available' => true,
                    'public' => true,
                ],
            ],
        ]);

        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_catalog_referrer',
            'referred_by' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard?catalog=assistant')
            ->assertOk()
            ->assertJsonPath('data.plans.0.id', 'assistant');

        $ids = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard?catalog=assistant')
            ->json('data.plans');

        $this->assertSame(['assistant'], array_column($ids, 'id'));
    }

    public function test_dashboard_shop_catalog_returns_shop_plans_only(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_shop_referrer',
            'referred_by' => null,
        ]);

        $ids = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard?catalog=shop')
            ->assertOk()
            ->json('data.plans');

        $this->assertSame(
            ['shop_basic', 'shop_premium', 'shop_profesional'],
            array_column($ids, 'id'),
        );
        $this->assertSame(__('humano_pricing.plans.shop_basic.name'), $ids[0]['name']);
        $this->assertSame('shop', $ids[0]['catalog']);
    }

    public function test_dashboard_mailer_catalog_returns_mailer_plans_without_checkout_urls(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_mailer_referrer',
            'referred_by' => null,
        ]);

        $ids = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard?catalog=mailer')
            ->assertOk()
            ->json('data.plans');

        $this->assertSame(
            ['mailer_basic', 'mailer_foundation', 'mailer_scale'],
            array_column($ids, 'id'),
        );
        $this->assertNull($ids[0]['referral_url']);
    }

    public function test_dashboard_ads_catalog_returns_ads_plan_only(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_ads_referrer',
            'referred_by' => null,
        ]);

        $ids = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard?catalog=ads')
            ->assertOk()
            ->json('data.plans');

        $this->assertSame(['ads'], array_column($ids, 'id'));
    }

    public function test_can_send_affiliate_invitation_via_api(): void
    {
        Mail::fake();

        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'starter',
                    'name' => 'Starter',
                    'checkout_url' => 'https://buy.stripe.com/test_starter',
                    'checkout_available' => true,
                ],
            ],
        ]);

        [, $team, $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_invite_referrer',
            'referred_by' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/invitations', [
                'invite_name' => 'Ana Cliente',
                'invite_email' => 'ana@example.com',
                'invite_plan' => 'starter',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invitee_email', 'ana@example.com');

        $this->assertDatabaseHas('affiliate_invitations', [
            'team_id' => $team->id,
            'invitee_email' => 'ana@example.com',
            'plan_id' => 'starter',
        ]);

        Mail::assertSent(AffiliatePurchaseInvitationMail::class);
    }

    public function test_can_claim_existing_subscriber_by_subscription_code(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_claim_referrer',
            'referred_by' => null,
        ]);

        $payingOwner = User::factory()->create([
            'email' => 'cliente.existente@example.com',
        ]);
        $payingTeam = Team::factory()->create([
            'name' => 'Cliente Existente',
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_claim_paying',
            'referred_by' => null,
        ]);
        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_claim_assistant',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/claim', [
                'subscription_code' => 'sub_claim_assistant',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Cliente Existente')
            ->assertJsonPath('data.email', 'cliente.existente@example.com')
            ->assertJsonPath('data.contracted', true);

        $this->assertSame('cus_claim_referrer', $payingTeam->fresh()->referred_by);
        $this->assertSame(
            'cus_claim_referrer',
            $payingTeam->subscriptions()->first()?->referred_by,
        );
    }

    public function test_can_claim_existing_subscriber_by_customer_code(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_claim_by_customer',
            'referred_by' => null,
        ]);

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_already_paying',
            'referred_by' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/claim', [
                'subscription_code' => 'cus_already_paying',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('cus_claim_by_customer', $payingTeam->fresh()->referred_by);
    }

    public function test_claim_rejects_unknown_subscription_code(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_claim_unknown',
            'referred_by' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/claim', [
                'subscription_code' => 'sub_does_not_exist',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_claim_rejects_own_subscription(): void
    {
        [, $team, $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_self_claim',
            'referred_by' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/claim', [
                'subscription_code' => 'cus_self_claim',
            ])
            ->assertStatus(422);

        $this->assertNull($team->fresh()->referred_by);
    }

    public function test_can_claim_second_subscription_for_same_client(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_claim_referrer',
            'referred_by' => null,
        ]);

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'name' => 'Cliente Dos Planes',
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_two_plans',
            'referred_by' => null,
        ]);
        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_claim_first',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
        ]);
        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'hosting',
            'stripe_id' => 'sub_claim_second',
            'stripe_status' => 'active',
            'stripe_price' => 'price_hosting_monthly',
            'quantity' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/claim', [
                'subscription_code' => 'sub_claim_first',
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/claim', [
                'subscription_code' => 'sub_claim_second',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('cus_claim_referrer', $payingTeam->fresh()->referred_by);
        $this->assertSame(
            'cus_claim_referrer',
            $payingTeam->subscriptions()->where('stripe_id', 'sub_claim_first')->value('referred_by'),
        );
        $this->assertSame(
            'cus_claim_referrer',
            $payingTeam->subscriptions()->where('stripe_id', 'sub_claim_second')->value('referred_by'),
        );
    }

    public function test_claiming_the_same_subscription_twice_is_idempotent(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_claim_referrer',
            'referred_by' => null,
        ]);

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_repeat_claim',
            'referred_by' => 'cus_claim_referrer',
        ]);
        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_already_yours',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
            'referred_by' => 'cus_claim_referrer',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/claim', [
                'subscription_code' => 'sub_already_yours',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_claiming_one_subscription_does_not_stamp_other_products(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_claim_referrer',
            'referred_by' => null,
        ]);

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_keep_other_plan',
            'referred_by' => null,
        ]);
        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'assistant',
            'stripe_id' => 'sub_only_this',
            'stripe_status' => 'active',
            'stripe_price' => 'price_assistant_monthly',
            'quantity' => 1,
        ]);
        $payingTeam->subscriptions()->create([
            'user_id' => $payingOwner->id,
            'type' => 'hosting',
            'stripe_id' => 'sub_leave_alone',
            'stripe_status' => 'active',
            'stripe_price' => 'price_hosting_monthly',
            'quantity' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/claim', [
                'subscription_code' => 'sub_only_this',
            ])
            ->assertOk();

        $this->assertSame(
            'cus_claim_referrer',
            $payingTeam->subscriptions()->where('stripe_id', 'sub_only_this')->value('referred_by'),
        );
        $this->assertNull(
            $payingTeam->subscriptions()->where('stripe_id', 'sub_leave_alone')->value('referred_by'),
        );
    }

    public function test_claim_does_not_overwrite_existing_referrer(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_late_referrer',
            'referred_by' => null,
        ]);

        $payingOwner = User::factory()->create();
        Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_already_referred',
            'referred_by' => 'cus_original_referrer',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/claim', [
                'subscription_code' => 'cus_already_referred',
            ])
            ->assertStatus(422);
    }
}
