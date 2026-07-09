<?php

namespace App\Jobs;

use App\Enums\AdPublishStatus;
use App\Models\PaidAdCampaignPlatform;
use App\Models\PaidAdMetricSnapshot;
use App\Services\Ads\AdPlatformGatewayFactory;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncPaidAdMetricsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int|null  $campaignPlatformId  Sync a single platform target, or all published ones when null.
     */
    public function __construct(
        private readonly ?int $campaignPlatformId = null,
        private readonly int $lookbackDays = 7,
    ) {}

    public function handle(AdPlatformGatewayFactory $gateways): void
    {
        $query = PaidAdCampaignPlatform::withoutGlobalScopes()
            ->with('connection')
            ->where('publish_status', AdPublishStatus::Published->value)
            ->whereNotNull('external_campaign_id');

        if ($this->campaignPlatformId !== null)
        {
            $query->whereKey($this->campaignPlatformId);
        }

        $from = now()->subDays($this->lookbackDays)->startOfDay();
        $to = now()->endOfDay();

        $query->each(function (PaidAdCampaignPlatform $campaignPlatform) use ($gateways, $from, $to): void
        {
            $this->syncPlatform($gateways, $campaignPlatform, $from, $to);
        });
    }

    private function syncPlatform(AdPlatformGatewayFactory $gateways, PaidAdCampaignPlatform $campaignPlatform, CarbonInterface $from, CarbonInterface $to): void
    {
        if ($campaignPlatform->connection === null)
        {
            return;
        }

        try
        {
            $metrics = $gateways->make($campaignPlatform->platform)->getMetrics($campaignPlatform, $from, $to);
        } catch (Throwable)
        {
            return;
        }

        foreach ($metrics as $metric)
        {
            PaidAdMetricSnapshot::query()->updateOrCreate(
                [
                    'paid_ad_campaign_platform_id' => $campaignPlatform->id,
                    'date' => $metric->date->format('Y-m-d'),
                ],
                [
                    'impressions' => $metric->impressions,
                    'clicks' => $metric->clicks,
                    'spend' => $metric->spend,
                    'conversions' => $metric->conversions,
                    'ctr' => $metric->ctr(),
                    'cpc' => $metric->cpc(),
                    'raw' => $metric->raw,
                ],
            );
        }

        $campaignPlatform->forceFill(['last_synced_at' => now()])->save();
    }
}
