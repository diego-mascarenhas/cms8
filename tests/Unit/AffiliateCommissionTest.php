<?php

namespace Tests\Unit;

use App\Models\Team;
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
}
