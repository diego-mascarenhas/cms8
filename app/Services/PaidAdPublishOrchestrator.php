<?php

namespace App\Services;

use App\Enums\AdPublishStatus;
use App\Enums\PaidAdCampaignStatus;
use App\Models\PaidAdCampaign;
use App\Models\PaidAdCampaignPlatform;
use App\Services\Ads\AdPlatformGatewayFactory;
use Throwable;

class PaidAdPublishOrchestrator
{
    public function __construct(private readonly AdPlatformGatewayFactory $gateways) {}

    /**
     * Publish every platform target of a campaign, tolerating partial failures.
     *
     * @return array{published: int, failed: int}
     */
    public function publish(PaidAdCampaign $campaign): array
    {
        $campaign->forceFill(['status' => PaidAdCampaignStatus::Publishing])->save();

        $published = 0;
        $failed = 0;

        foreach ($campaign->platforms as $campaignPlatform)
        {
            $this->publishPlatform($campaignPlatform)
                ? $published++
                : $failed++;
        }

        $campaign->forceFill([
            'status' => $this->resolveCampaignStatus($published, $failed),
        ])->save();

        return ['published' => $published, 'failed' => $failed];
    }

    public function publishPlatform(PaidAdCampaignPlatform $campaignPlatform): bool
    {
        $campaignPlatform->forceFill([
            'publish_status' => AdPublishStatus::Publishing,
            'publish_error' => null,
        ])->save();

        try
        {
            $result = $this->gateways->make($campaignPlatform->platform)->publish($campaignPlatform);
        } catch (Throwable $e)
        {
            $campaignPlatform->forceFill([
                'publish_status' => AdPublishStatus::Failed,
                'publish_error' => $e->getMessage(),
            ])->save();

            return false;
        }

        if (! $result->success)
        {
            $campaignPlatform->forceFill([
                'publish_status' => AdPublishStatus::Failed,
                'publish_error' => $result->error,
            ])->save();

            return false;
        }

        $campaignPlatform->forceFill([
            'publish_status' => AdPublishStatus::Published,
            'external_campaign_id' => $result->externalCampaignId,
            'platform_payload' => array_merge((array) $campaignPlatform->platform_payload, ['publish_response' => $result->payload]),
            'last_synced_at' => now(),
        ])->save();

        return true;
    }

    private function resolveCampaignStatus(int $published, int $failed): PaidAdCampaignStatus
    {
        if ($published === 0 && $failed > 0)
        {
            return PaidAdCampaignStatus::Failed;
        }

        return PaidAdCampaignStatus::Active;
    }
}
