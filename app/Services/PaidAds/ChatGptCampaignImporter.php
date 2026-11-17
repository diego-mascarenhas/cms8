<?php

namespace App\Services\PaidAds;

use App\Enums\AdConnectionStatus;
use App\Enums\AdPlatform;
use App\Enums\AdPublishStatus;
use App\Enums\PaidAdCampaignStatus;
use App\Enums\PaidAdObjective;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaign;
use App\Models\PaidAdCampaignPlatform;
use App\Models\Team;
use App\Services\Ads\ChatGptAdsGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ChatGptCampaignImporter
{
    public function __construct(private readonly ChatGptAdsGateway $gateway) {}

    /**
     * Pull ChatGPT campaigns into the team list. Repeated calls within a minute reuse the last result.
     */
    public function importForTeam(Team $team): int
    {
        return (int) Cache::remember('chatgpt-campaign-import:'.$team->id, 60, function () use ($team): int
        {
            return $this->pull($team);
        });
    }

    public function pull(Team $team): int
    {
        $connections = AdPlatformConnection::query()
            ->where('team_id', $team->id)
            ->where('platform', AdPlatform::ChatGpt)
            ->where('status', AdConnectionStatus::Active)
            ->whereNotNull('ad_account_id')
            ->get();

        $imported = 0;

        foreach ($connections as $connection)
        {
            try
            {
                $remoteCampaigns = $this->gateway->forTeam($team)->listRemoteCampaigns($connection);
            } catch (Throwable)
            {
                continue;
            }

            foreach ($remoteCampaigns as $remote)
            {
                if ($this->upsert($team, $connection, $remote))
                {
                    $imported++;
                }
            }
        }

        return $imported;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function upsert(Team $team, AdPlatformConnection $connection, array $remote): bool
    {
        $externalId = trim((string) ($remote['id'] ?? ''));
        $name = trim((string) ($remote['name'] ?? ''));
        if ($externalId === '' || $name === '')
        {
            return false;
        }

        $platformRow = PaidAdCampaignPlatform::query()
            ->where('platform', AdPlatform::ChatGpt)
            ->where('external_campaign_id', $externalId)
            ->whereHas('campaign', fn ($query) => $query->where('team_id', $team->id))
            ->first();

        $campaign = $platformRow?->campaign;
        if ($campaign !== null && (int) $campaign->team_id !== (int) $team->id)
        {
            return false;
        }

        $groups = is_array($remote['ad_groups'] ?? null) ? $remote['ad_groups'] : [];
        $group = is_array($groups[0] ?? null) ? $groups[0] : [];
        $ads = is_array($group['ads'] ?? null) ? $group['ads'] : [];
        $ad = is_array($ads[0] ?? null) ? $ads[0] : [];
        $creative = is_array($ad['creative'] ?? null) ? $ad['creative'] : [];
        $hints = is_array($group['context_hints'] ?? null) ? $group['context_hints'] : [];

        $attributes = [
            'name' => mb_substr($name, 0, 255),
            'objective' => $this->objective((string) ($remote['bidding_type'] ?? 'clicks')),
            'status' => $this->status((string) ($remote['status'] ?? 'paused')),
            'budget_type' => 'lifetime',
            'budget_amount' => round(((int) data_get($remote, 'budget.lifetime_spend_limit_micros', 0)) / 1_000_000, 2),
            'currency' => (string) (data_get($connection->metadata, 'currency_code') ?: 'EUR'),
            'start_at' => $this->timestamp($remote['start_time'] ?? null),
            'end_at' => $this->timestamp($remote['end_time'] ?? null),
            'targeting' => [
                'interests' => implode(', ', array_map('strval', $hints)),
            ],
            'creative' => $this->creative($campaign, $creative),
        ];

        if ($campaign === null)
        {
            $campaign = new PaidAdCampaign;
            $campaign->team_id = $team->id;
            $campaign->created_by = $connection->user_id;
            $campaign->forceFill($attributes);
            if (isset($remote['created_at']) && is_numeric($remote['created_at']))
            {
                $campaign->created_at = Carbon::createFromTimestamp((int) $remote['created_at']);
            }
            $campaign->save();
        } else
        {
            $campaign->forceFill($attributes)->save();
        }

        $campaign->platforms()->updateOrCreate(
            ['platform' => AdPlatform::ChatGpt->value],
            [
                'ad_platform_connection_id' => $connection->id,
                'external_campaign_id' => $externalId,
                'publish_status' => AdPublishStatus::Published,
                'publish_error' => null,
                'platform_payload' => [
                    'chatgpt' => array_filter([
                        'campaign_id' => $externalId,
                        'ad_group_id' => $group['id'] ?? null,
                        'ad_id' => $ad['id'] ?? null,
                        'review_status' => $ad['review_status'] ?? null,
                        'imported' => true,
                    ]),
                ],
                'last_synced_at' => now(),
            ],
        );

        return true;
    }

    private function objective(string $biddingType): PaidAdObjective
    {
        return match ($biddingType)
        {
            'impressions' => PaidAdObjective::Awareness,
            'conversions' => PaidAdObjective::Sales,
            default => PaidAdObjective::Traffic,
        };
    }

    private function status(string $status): PaidAdCampaignStatus
    {
        return match ($status)
        {
            'active' => PaidAdCampaignStatus::Active,
            'archived' => PaidAdCampaignStatus::Archived,
            default => PaidAdCampaignStatus::Paused,
        };
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_numeric($value))
        {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value);
    }

    /**
     * @param  array<string, mixed>  $remoteCreative
     * @return array<string, mixed>
     */
    private function creative(?PaidAdCampaign $campaign, array $remoteCreative): array
    {
        $current = (array) ($campaign?->creative ?? []);
        $current['headline'] = (string) ($remoteCreative['title'] ?? $current['headline'] ?? '');
        $current['body'] = (string) ($remoteCreative['body'] ?? $current['body'] ?? '');
        $current['url'] = (string) ($remoteCreative['target_url'] ?? $current['url'] ?? '');

        return $current;
    }
}
