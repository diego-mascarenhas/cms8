<?php

namespace Tests\Feature;

use App\Jobs\LoadTeamBusinessInsightsJob;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadTeamBusinessInsightsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_job_sets_fallback_report_and_clears_phase_flags(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $team->setSetting('business_config', [
            'business_name' => 'Acme',
            '_insights_phase' => 'market_data',
            '_insights_requested_at' => now()->subMinute()->toIso8601String(),
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);

        $job = new LoadTeamBusinessInsightsJob($team->id);
        $job->failed(new \RuntimeException('Timeout'));

        $team->refresh();
        $config = $team->getSetting('business_config', []);

        $this->assertIsArray($config);
        $this->assertIsArray($config['_insights'] ?? null);
        $this->assertNotEmpty($config['_insights']['potential_clients_summary'] ?? '');
        $this->assertArrayNotHasKey('_insights_phase', $config);
        $this->assertArrayNotHasKey('_insights_requested_at', $config);
    }
}
