<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamHasCompletedBusinessConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_false_when_no_business_config_setting(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($team->hasCompletedBusinessConfiguration());
    }

    public function test_returns_false_when_business_name_is_empty(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $team->setSetting('business_config', ['business_industry' => 'Retail'], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $this->assertFalse($team->fresh()->hasCompletedBusinessConfiguration());
    }

    public function test_returns_true_when_business_name_is_set(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $team->setSetting('business_config', ['business_name' => 'Acme Corp'], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $this->assertTrue($team->fresh()->hasCompletedBusinessConfiguration());
    }
}
