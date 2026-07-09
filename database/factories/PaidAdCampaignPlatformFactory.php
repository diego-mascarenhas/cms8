<?php

namespace Database\Factories;

use App\Enums\AdPlatform;
use App\Enums\AdPublishStatus;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaign;
use App\Models\PaidAdCampaignPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaidAdCampaignPlatform>
 */
class PaidAdCampaignPlatformFactory extends Factory
{
    protected $model = PaidAdCampaignPlatform::class;

    public function definition(): array
    {
        return [
            'paid_ad_campaign_id' => PaidAdCampaign::factory(),
            'ad_platform_connection_id' => AdPlatformConnection::factory(),
            'platform' => AdPlatform::GoogleAds,
            'external_campaign_id' => null,
            'publish_status' => AdPublishStatus::Pending,
            'publish_error' => null,
            'platform_payload' => [],
        ];
    }

    public function published(string $externalId = 'ext-123'): static
    {
        return $this->state(fn () => [
            'publish_status' => AdPublishStatus::Published,
            'external_campaign_id' => $externalId,
        ]);
    }
}
