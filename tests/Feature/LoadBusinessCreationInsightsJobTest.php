<?php

namespace Tests\Feature;

use App\Jobs\LoadBusinessCreationInsightsJob;
use App\Models\BusinessCreationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadBusinessCreationInsightsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_job_sets_fallback_report_and_clears_phase_flags(): void
    {
        $session = BusinessCreationSession::createWithToken();
        $session->update([
            'config' => [
                '_insights_phase' => 'market_data',
                '_insights_requested_at' => now()->subMinute()->toIso8601String(),
            ],
        ]);

        $job = new LoadBusinessCreationInsightsJob($session->id);
        $job->failed(new \RuntimeException('Timeout'));

        $updatedConfig = $session->fresh()->config;

        $this->assertIsArray($updatedConfig['_insights'] ?? null);
        $this->assertNotEmpty($updatedConfig['_insights']['potential_clients_summary'] ?? '');
        $this->assertArrayNotHasKey('_insights_phase', $updatedConfig);
        $this->assertArrayNotHasKey('_insights_requested_at', $updatedConfig);
    }
}
