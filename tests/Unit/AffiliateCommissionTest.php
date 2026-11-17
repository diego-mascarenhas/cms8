<?php

namespace Tests\Unit;

use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Support\AffiliateCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_percent_defaults_to_config_when_platform_team_has_no_setting(): void
    {
        config([
            'humano_pricing.affiliate_commission_percent' => 30,
            'humano_pricing.platform_team_id' => 0,
        ]);

        $this->assertSame(30.0, AffiliateCommission::percent());
        $this->assertSame('30', AffiliateCommission::displayPercent());
    }

    public function test_percent_reads_platform_team_setting(): void
    {
        $team = Team::factory()->create();

        config([
            'humano_pricing.affiliate_commission_percent' => 30,
            'humano_pricing.platform_team_id' => $team->id,
        ]);

        $team->setSetting('affiliate_commission_percent', 25, [
            'group' => 'affiliates',
            'type' => 'integer',
        ]);

        $this->assertSame(25.0, AffiliateCommission::percent());
    }

    public function test_is_platform_team_matches_configured_id(): void
    {
        $team = Team::factory()->create();
        $other = Team::factory()->create();

        config(['humano_pricing.platform_team_id' => $team->id]);

        $this->assertTrue(AffiliateCommission::isPlatformTeam($team));
        $this->assertFalse(AffiliateCommission::isPlatformTeam($other));
    }

    public function test_percent_for_catalog_uses_override_then_global(): void
    {
        config([
            'humano_pricing.affiliate_commission_percent' => 30,
            'humano_pricing.platform_team_id' => 0,
            'humano_pricing.affiliate_commission_percent_by_catalog' => [
                'mailer' => 18,
            ],
        ]);

        $this->assertSame(18.0, AffiliateCommission::percentForCatalog('mailer'));
        $this->assertSame(30.0, AffiliateCommission::percentForCatalog('ads'));
        $this->assertSame(30.0, AffiliateCommission::percentForCatalog(null));
    }

    public function test_percent_for_plan_prefers_plan_key_over_catalog(): void
    {
        config([
            'humano_pricing.affiliate_commission_percent' => 30,
            'humano_pricing.platform_team_id' => 0,
            'humano_pricing.affiliate_commission_percent_by_catalog' => [
                'mailer' => 18,
            ],
        ]);

        $this->assertSame(12.0, AffiliateCommission::percentForPlan([
            'id' => 'mailer_scale',
            'catalog' => 'mailer',
            'affiliate_commission_percent' => 12,
        ]));

        $this->assertSame(18.0, AffiliateCommission::percentForPlan([
            'id' => 'mailer_foundation',
            'catalog' => 'mailer',
        ]));
    }

    public function test_percent_for_subscription_resolves_plan_from_type(): void
    {
        config([
            'humano_pricing.affiliate_commission_percent' => 15,
            'humano_pricing.platform_team_id' => 0,
            'humano_pricing.affiliate_commission_percent_by_catalog' => [
                'mailer' => 15,
                'hosting' => 20,
                'vps' => 25,
            ],
            'humano_pricing.plans' => [
                [
                    'id' => 'mailer_foundation',
                    'catalog' => 'mailer',
                    'subscription_type' => 'mailer',
                    'public' => false,
                    'checkout_available' => true,
                ],
            ],
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $subscription = $team->subscriptions()->create([
            'user_id' => $owner->id,
            'type' => 'mailer',
            'stripe_id' => 'sub_mailer_pct',
            'stripe_status' => 'active',
            'stripe_price' => 'price_mailer',
            'quantity' => 1,
        ]);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertSame(15.0, AffiliateCommission::percentForSubscription($subscription));
    }

    public function test_percent_for_hosting_and_vps_subscription_types(): void
    {
        config([
            'humano_pricing.affiliate_commission_percent' => 15,
            'humano_pricing.platform_team_id' => 0,
            'humano_pricing.affiliate_commission_percent_by_catalog' => [
                'hosting' => 20,
                'vps' => 25,
            ],
            'humano_pricing.plans' => [],
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $hosting = $team->subscriptions()->create([
            'user_id' => $owner->id,
            'type' => 'hosting',
            'stripe_id' => 'sub_hosting_pct',
            'stripe_status' => 'active',
            'stripe_price' => 'price_hosting',
            'quantity' => 1,
        ]);

        $vps = $team->subscriptions()->create([
            'user_id' => $owner->id,
            'type' => 'vps',
            'stripe_id' => 'sub_vps_pct',
            'stripe_status' => 'active',
            'stripe_price' => 'price_vps',
            'quantity' => 1,
        ]);

        $this->assertSame(20.0, AffiliateCommission::percentForSubscription($hosting));
        $this->assertSame(25.0, AffiliateCommission::percentForSubscription($vps));
        $this->assertSame(15.0, AffiliateCommission::percentForCatalog('assistant'));
    }
}
