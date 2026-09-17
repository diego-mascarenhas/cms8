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

    public function pause(PaidAdCampaign $campaign): void
    {
        $this->setPlatformStatuses($campaign, pause: true);
    }

    public function resume(PaidAdCampaign $campaign): void
    {
        $this->setPlatformStatuses($campaign, pause: false);
    }

    public function publishPlatform(PaidAdCampaignPlatform $campaignPlatform): bool
    {
        $campaignPlatform->forceFill([
            'publish_status' => AdPublishStatus::Publishing,
            'publish_error' => null,
        ])->save();

        try
        {
            $result = $this->gateways->make($campaignPlatform->platform)
                ->forTeam($campaignPlatform->connection?->team ?? $campaign->team)
                ->publish($campaignPlatform);
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

    private function setPlatformStatuses(PaidAdCampaign $campaign, bool $pause): void
    {
        $campaign->loadMissing('platforms.connection');

        foreach ($campaign->platforms as $campaignPlatform)
        {
            try
            {
                $gateway = $this->gateways->make($campaignPlatform->platform)
                    ->forTeam($campaignPlatform->connection?->team ?? $campaign->team);

                $pause
                    ? $gateway->pause($campaignPlatform)
                    : $gateway->resume($campaignPlatform);
            } catch (Throwable)
            {
                continue;
            }
        }
    }
}
