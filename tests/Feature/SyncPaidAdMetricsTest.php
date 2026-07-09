<?php

namespace Tests\Feature;

use App\Enums\AdPlatform;
use App\Jobs\SyncPaidAdMetricsJob;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaign;
use App\Models\PaidAdCampaignPlatform;
use App\Models\PaidAdMetricSnapshot;
use App\Services\PaidAdMetricsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncPaidAdMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_stores_metric_snapshots_from_google(): void
    {
        Http::fake([
            'googleads.googleapis.com/*' => Http::response([
                'results' => [[
                    'segments' => ['date' => now()->format('Y-m-d')],
                    'metrics' => ['impressions' => 1000, 'clicks' => 50, 'costMicros' => 12_000_000, 'conversions' => 5],
                ]],
            ], 200),
        ]);

        $campaign = PaidAdCampaign::factory()->create();
        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $campaign->team_id,
            'platform' => AdPlatform::GoogleAds,
        ]);
        $campaignPlatform = PaidAdCampaignPlatform::factory()->published('customers/1/campaigns/2')->create([
            'paid_ad_campaign_id' => $campaign->id,
            'ad_platform_connection_id' => $connection->id,
            'platform' => AdPlatform::GoogleAds,
        ]);

        (new SyncPaidAdMetricsJob($campaignPlatform->id))->handle(app(\App\Services\Ads\AdPlatformGatewayFactory::class));

        $this->assertDatabaseHas('paid_ad_metric_snapshots', [
            'paid_ad_campaign_platform_id' => $campaignPlatform->id,
            'impressions' => 1000,
            'clicks' => 50,
            'spend' => 12.00,
            'conversions' => 5,
        ]);
    }

    public function test_aggregator_sums_metrics_for_campaign(): void
    {
        $campaign = PaidAdCampaign::factory()->create();
        $campaignPlatform = PaidAdCampaignPlatform::factory()->published()->create([
            'paid_ad_campaign_id' => $campaign->id,
            'platform' => AdPlatform::Meta,
        ]);

        PaidAdMetricSnapshot::factory()->create([
            'paid_ad_campaign_platform_id' => $campaignPlatform->id,
            'date' => now()->subDay()->toDateString(),
            'impressions' => 200,
            'clicks' => 10,
            'spend' => 30,
            'conversions' => 2,
        ]);
        PaidAdMetricSnapshot::factory()->create([
            'paid_ad_campaign_platform_id' => $campaignPlatform->id,
            'date' => now()->toDateString(),
            'impressions' => 300,
            'clicks' => 15,
            'spend' => 45,
            'conversions' => 3,
        ]);

        $totals = app(PaidAdMetricsAggregator::class)->forCampaign($campaign->fresh());

        $this->assertSame(500, $totals['impressions']);
        $this->assertSame(25, $totals['clicks']);
        $this->assertSame(75.0, $totals['spend']);
        $this->assertSame(5, $totals['conversions']);
    }
}
