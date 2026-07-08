<?php

namespace Tests\Feature;

use App\Enums\AdPlatform;
use App\Enums\AdPublishStatus;
use App\Enums\PaidAdCampaignStatus;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaign;
use App\Models\PaidAdCampaignPlatform;
use App\Services\PaidAdPublishOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishPaidAdCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrator_publishes_google_campaign(): void
    {
        Http::fake([
            'googleads.googleapis.com/*' => Http::response([
                'results' => [['resourceName' => 'customers/123/campaigns/456']],
            ], 200),
        ]);

        $campaign = PaidAdCampaign::factory()->create();
        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $campaign->team_id,
            'platform' => AdPlatform::GoogleAds,
        ]);
        $campaignPlatform = PaidAdCampaignPlatform::factory()->create([
            'paid_ad_campaign_id' => $campaign->id,
            'ad_platform_connection_id' => $connection->id,
            'platform' => AdPlatform::GoogleAds,
        ]);

        $result = app(PaidAdPublishOrchestrator::class)->publish($campaign->fresh('platforms.connection'));

        $this->assertSame(1, $result['published']);
        $this->assertSame(0, $result['failed']);

        $campaignPlatform->refresh();
        $this->assertSame(AdPublishStatus::Published, $campaignPlatform->publish_status);
        $this->assertSame('customers/123/campaigns/456', $campaignPlatform->external_campaign_id);
        $this->assertSame(PaidAdCampaignStatus::Active, $campaign->fresh()->status);
    }

    public function test_orchestrator_marks_platform_failed_on_api_error(): void
    {
        Http::fake([
            'googleads.googleapis.com/*' => Http::response(['error' => 'bad request'], 400),
        ]);

        $campaign = PaidAdCampaign::factory()->create();
        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $campaign->team_id,
            'platform' => AdPlatform::GoogleAds,
        ]);
        $campaignPlatform = PaidAdCampaignPlatform::factory()->create([
            'paid_ad_campaign_id' => $campaign->id,
            'ad_platform_connection_id' => $connection->id,
            'platform' => AdPlatform::GoogleAds,
        ]);

        $result = app(PaidAdPublishOrchestrator::class)->publish($campaign->fresh('platforms.connection'));

        $this->assertSame(0, $result['published']);
        $this->assertSame(1, $result['failed']);

        $campaignPlatform->refresh();
        $this->assertSame(AdPublishStatus::Failed, $campaignPlatform->publish_status);
        $this->assertNotNull($campaignPlatform->publish_error);
        $this->assertSame(PaidAdCampaignStatus::Failed, $campaign->fresh()->status);
    }
}
