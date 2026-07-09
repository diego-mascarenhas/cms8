<?php

namespace App\Services\Ads;

use App\Enums\AdConnectionStatus;
use App\Enums\AdPlatform;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaignPlatform;
use App\Models\User;
use App\Services\Ads\DTO\AdAccountDTO;
use App\Services\Ads\DTO\AdCampaignResult;
use App\Services\Ads\DTO\AdMetricsDTO;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TikTokAdsGateway extends AbstractAdPlatformGateway
{
    private const BASE = 'https://business-api.tiktok.com/open_api/v1.3';

    public function platform(): AdPlatform
    {
        return AdPlatform::TikTok;
    }

    protected function configKey(): string
    {
        return 'tiktok_ads';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://business-api.tiktok.com/portal/auth';
    }

    protected function tokenEndpoint(): string
    {
        return self::BASE.'/oauth2/access_token/';
    }

    protected function scopes(): array
    {
        return [];
    }

    protected function extraAuthorizationParams(): array
    {
        return ['app_id' => $this->clientId()];
    }

    /**
     * TikTok uses a JSON body with app_id/secret/auth_code instead of standard OAuth params.
     */
    public function exchangeCode(User $user, string $authCode): AdPlatformConnection
    {
        $response = Http::acceptJson()->post($this->tokenEndpoint(), [
            'app_id' => $this->clientId(),
            'secret' => $this->clientSecret(),
            'auth_code' => $authCode,
        ]);

        if ($response->failed() || (int) $response->json('code', -1) !== 0)
        {
            throw new RuntimeException('TikTok token exchange failed: '.$response->body());
        }

        $data = (array) $response->json('data', []);

        return AdPlatformConnection::query()->updateOrCreate(
            [
                'team_id' => $user->currentTeam?->id,
                'platform' => $this->platform(),
                'ad_account_id' => null,
            ],
            [
                'user_id' => $user->id,
                'access_token' => $data['access_token'] ?? null,
                'scopes' => array_map('strval', (array) ($data['scope'] ?? [])),
                'status' => AdConnectionStatus::PendingAccount,
                'metadata' => ['advertiser_ids' => $data['advertiser_ids'] ?? []],
            ],
        );
    }

    /**
     * @return array<int, AdAccountDTO>
     */
    public function listAdAccounts(AdPlatformConnection $connection): array
    {
        $advertiserIds = (array) data_get($connection->metadata, 'advertiser_ids', []);

        if ($advertiserIds === [])
        {
            return [];
        }

        $response = $this->tikTokClient($connection)->get(self::BASE.'/advertiser/info/', [
            'advertiser_ids' => json_encode(array_values($advertiserIds)),
        ]);

        if ($response->failed())
        {
            return array_map(fn ($id): AdAccountDTO => new AdAccountDTO((string) $id, 'Advertiser '.$id), $advertiserIds);
        }

        return array_map(function (array $row): AdAccountDTO
        {
            return new AdAccountDTO(
                (string) ($row['advertiser_id'] ?? ''),
                (string) ($row['name'] ?? 'Advertiser'),
                $row['currency'] ?? null,
            );
        }, (array) $response->json('data.list', []));
    }

    public function publish(PaidAdCampaignPlatform $campaignPlatform): AdCampaignResult
    {
        $connection = $campaignPlatform->connection;
        $campaign = $campaignPlatform->campaign;

        if ($connection === null || ! $connection->isUsable())
        {
            return AdCampaignResult::fail(__('The TikTok connection is not ready.'));
        }

        try
        {
            $response = $this->tikTokClient($connection)->post(self::BASE.'/campaign/create/', [
                'advertiser_id' => $connection->ad_account_id,
                'campaign_name' => $campaign->name,
                'objective_type' => $this->mapObjective($campaign->objective?->value),
                'budget_mode' => $campaign->budget_type === 'lifetime' ? 'BUDGET_MODE_TOTAL' : 'BUDGET_MODE_DAY',
                'budget' => (float) ($campaign->budget_amount ?? 0),
                'operation_status' => 'DISABLE',
            ]);

            if ($response->failed() || (int) $response->json('code', -1) !== 0)
            {
                return AdCampaignResult::fail('TikTok: '.$response->body());
            }

            return AdCampaignResult::ok((string) $response->json('data.campaign_id', ''), $response->json() ?? []);
        } catch (Throwable $e)
        {
            return AdCampaignResult::fail('TikTok: '.$e->getMessage());
        }
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

        $response = $this->tikTokClient($connection)->get(self::BASE.'/report/integrated/get/', [
            'advertiser_id' => $connection->ad_account_id,
            'report_type' => 'BASIC',
            'data_level' => 'AUCTION_CAMPAIGN',
            'dimensions' => json_encode(['campaign_id', 'stat_time_day']),
            'metrics' => json_encode(['impressions', 'clicks', 'spend', 'conversion']),
            'start_date' => $from->format('Y-m-d'),
            'end_date' => $to->format('Y-m-d'),
            'filtering' => json_encode([['field_name' => 'campaign_ids', 'filter_type' => 'IN', 'filter_value' => json_encode([$campaignPlatform->external_campaign_id])]]),
        ]);

        if ($response->failed())
        {
            return [];
        }

        return array_map(function (array $row): AdMetricsDTO
        {
            $metrics = $row['metrics'] ?? [];
            $dimensions = $row['dimensions'] ?? [];

            return new AdMetricsDTO(
                Carbon::parse($dimensions['stat_time_day'] ?? now()->format('Y-m-d')),
                (int) ($metrics['impressions'] ?? 0),
                (int) ($metrics['clicks'] ?? 0),
                (float) ($metrics['spend'] ?? 0),
                (int) round((float) ($metrics['conversion'] ?? 0)),
                $row,
            );
        }, (array) $response->json('data.list', []));
    }

    private function tikTokClient(AdPlatformConnection $connection): PendingRequest
    {
        $this->forTeam($connection->team);

        if ($connection->isTokenExpired())
        {
            $connection->refresh();
        }

        return Http::acceptJson()->withHeaders([
            'Access-Token' => (string) $connection->access_token,
        ]);
    }

    private function mapObjective(?string $objective): string
    {
        return match ($objective)
        {
            'awareness' => 'REACH',
            'traffic' => 'TRAFFIC',
            'engagement' => 'ENGAGEMENT',
            'leads' => 'LEAD_GENERATION',
            'sales' => 'CONVERSIONS',
            'app_promotion' => 'APP_PROMOTION',
            default => 'TRAFFIC',
        };
    }
}
