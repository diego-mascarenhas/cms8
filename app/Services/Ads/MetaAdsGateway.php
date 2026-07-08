<?php

namespace App\Services\Ads;

use App\Enums\AdPlatform;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaignPlatform;
use App\Services\Ads\DTO\AdAccountDTO;
use App\Services\Ads\DTO\AdCampaignResult;
use App\Services\Ads\DTO\AdMetricsDTO;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Throwable;

class MetaAdsGateway extends AbstractAdPlatformGateway
{
    public function platform(): AdPlatform
    {
        return AdPlatform::Meta;
    }

    protected function configKey(): string
    {
        return 'meta_ads';
    }

    protected function apiVersion(): string
    {
        return (string) config('services.meta_ads.api_version', 'v21.0');
    }

    protected function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->apiVersion().'/'.ltrim($path, '/');
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://www.facebook.com/'.$this->apiVersion().'/dialog/oauth';
    }

    protected function tokenEndpoint(): string
    {
        return $this->graphUrl('oauth/access_token');
    }

    protected function scopes(): array
    {
        return ['ads_management', 'ads_read', 'business_management'];
    }

    /**
     * @return array<int, AdAccountDTO>
     */
    public function listAdAccounts(AdPlatformConnection $connection): array
    {
        $response = $this->authorizedClient($connection)->get($this->graphUrl('me/adaccounts'), [
            'fields' => 'id,account_id,name,currency,account_status',
        ]);

        if ($response->failed())
        {
            return [];
        }

        return array_map(function (array $row): AdAccountDTO
        {
            return new AdAccountDTO(
                (string) ($row['id'] ?? $row['account_id'] ?? ''),
                (string) ($row['name'] ?? ''),
                $row['currency'] ?? null,
                isset($row['account_status']) ? (string) $row['account_status'] : null,
            );
        }, (array) $response->json('data', []));
    }

    public function publish(PaidAdCampaignPlatform $campaignPlatform): AdCampaignResult
    {
        $connection = $campaignPlatform->connection;
        $campaign = $campaignPlatform->campaign;

        if ($connection === null || ! $connection->isUsable())
        {
            return AdCampaignResult::fail(__('The Meta connection is not ready.'));
        }

        try
        {
            $accountId = $this->normalizeAccountId((string) $connection->ad_account_id);

            $response = $this->authorizedClient($connection)->asForm()->post($this->graphUrl($accountId.'/campaigns'), [
                'name' => $campaign->name,
                'objective' => $this->mapObjective($campaign->objective?->value),
                'status' => 'PAUSED',
                'special_ad_categories' => json_encode([]),
            ]);

            if ($response->failed())
            {
                return AdCampaignResult::fail('Meta: '.$response->body());
            }

            return AdCampaignResult::ok((string) $response->json('id', ''), $response->json() ?? []);
        } catch (Throwable $e)
        {
            return AdCampaignResult::fail('Meta: '.$e->getMessage());
        }
    }

    public function pause(PaidAdCampaignPlatform $campaignPlatform): void
    {
        $this->setStatus($campaignPlatform, 'PAUSED');
    }

    public function resume(PaidAdCampaignPlatform $campaignPlatform): void
    {
        $this->setStatus($campaignPlatform, 'ACTIVE');
    }

    /**
     * @return array<int, AdMetricsDTO>
     */
    public function getMetrics(PaidAdCampaignPlatform $campaignPlatform, CarbonInterface $from, CarbonInterface $to): array
    {
        $connection = $campaignPlatform->connection;

        if ($connection === null || $campaignPlatform->external_campaign_id === null)
        {
            return [];
        }

        $response = $this->authorizedClient($connection)->get($this->graphUrl($campaignPlatform->external_campaign_id.'/insights'), [
            'fields' => 'impressions,clicks,spend,actions',
            'time_range' => json_encode(['since' => $from->format('Y-m-d'), 'until' => $to->format('Y-m-d')]),
            'time_increment' => 1,
        ]);

        if ($response->failed())
        {
            return [];
        }

        return array_map(function (array $row): AdMetricsDTO
        {
            return new AdMetricsDTO(
                Carbon::parse($row['date_start'] ?? now()->format('Y-m-d')),
                (int) ($row['impressions'] ?? 0),
                (int) ($row['clicks'] ?? 0),
                (float) ($row['spend'] ?? 0),
                $this->extractConversions($row['actions'] ?? []),
                $row,
            );
        }, (array) $response->json('data', []));
    }

    private function setStatus(PaidAdCampaignPlatform $campaignPlatform, string $status): void
    {
        $connection = $campaignPlatform->connection;

        if ($connection === null || $campaignPlatform->external_campaign_id === null)
        {
            return;
        }

        $this->authorizedClient($connection)->asForm()->post($this->graphUrl($campaignPlatform->external_campaign_id), [
            'status' => $status,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     */
    private function extractConversions(array $actions): int
    {
        foreach ($actions as $action)
        {
            if (($action['action_type'] ?? '') === 'lead' || ($action['action_type'] ?? '') === 'purchase')
            {
                return (int) round((float) ($action['value'] ?? 0));
            }
        }

        return 0;
    }

    private function mapObjective(?string $objective): string
    {
        return match ($objective)
        {
            'awareness' => 'OUTCOME_AWARENESS',
            'traffic' => 'OUTCOME_TRAFFIC',
            'engagement' => 'OUTCOME_ENGAGEMENT',
            'leads' => 'OUTCOME_LEADS',
            'sales' => 'OUTCOME_SALES',
            'app_promotion' => 'OUTCOME_APP_PROMOTION',
            default => 'OUTCOME_TRAFFIC',
        };
    }

    private function normalizeAccountId(string $id): string
    {
        return str_starts_with($id, 'act_') ? $id : 'act_'.$id;
    }

    /**
     * @param  array<string, mixed>  $token
     */
    protected function metadataFromToken(array $token): array
    {
        return array_filter(['token_type' => Arr::get($token, 'token_type')]);
    }
}
