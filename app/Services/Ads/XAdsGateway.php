<?php

namespace App\Services\Ads;

use App\Enums\AdPlatform;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaignPlatform;
use App\Services\Ads\DTO\AdAccountDTO;
use App\Services\Ads\DTO\AdCampaignResult;
use App\Services\Ads\DTO\AdMetricsDTO;
use Carbon\CarbonInterface;
use Throwable;

/**
 * X (Twitter) Ads API integration.
 *
 * X Ads API access is heavily restricted and requires an approved developer
 * account with the Ads API product. The integration is gated behind the
 * `services.x_ads.enabled` feature flag and remains inactive until approved.
 */
class XAdsGateway extends AbstractAdPlatformGateway
{
    private const BASE = 'https://ads-api.x.com/12';

    public function platform(): AdPlatform
    {
        return AdPlatform::X;
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.x_ads.enabled', false) && parent::isConfigured();
    }

    protected function configKey(): string
    {
        return 'x_ads';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://twitter.com/i/oauth2/authorize';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://api.twitter.com/2/oauth2/token';
    }

    protected function scopes(): array
    {
        return ['tweet.read', 'users.read', 'offline.access'];
    }

    protected function extraAuthorizationParams(): array
    {
        return ['code_challenge' => 'challenge', 'code_challenge_method' => 'plain'];
    }

    protected function extraTokenParams(): array
    {
        return ['code_verifier' => 'challenge'];
    }

    /**
     * @return array<int, AdAccountDTO>
     */
    public function listAdAccounts(AdPlatformConnection $connection): array
    {
        $response = $this->authorizedClient($connection)->get(self::BASE.'/accounts');

        if ($response->failed())
        {
            return [];
        }

        return array_map(function (array $row): AdAccountDTO
        {
            return new AdAccountDTO(
                (string) ($row['id'] ?? ''),
                (string) ($row['name'] ?? 'Account'),
            );
        }, (array) $response->json('data', []));
    }

    public function publish(PaidAdCampaignPlatform $campaignPlatform): AdCampaignResult
    {
        $connection = $campaignPlatform->connection;
        $campaign = $campaignPlatform->campaign;

        if ($connection === null || ! $connection->isUsable())
        {
            return AdCampaignResult::fail(__('The X connection is not ready.'));
        }

        try
        {
            $response = $this->authorizedClient($connection)->post(self::BASE.'/accounts/'.$connection->ad_account_id.'/campaigns', [
                'name' => $campaign->name,
                'funding_instrument_id' => data_get($connection->metadata, 'funding_instrument_id'),
                'daily_budget_amount_local_micro' => (int) round((float) ($campaign->budget_amount ?? 0) * 1_000_000),
                'entity_status' => 'PAUSED',
            ]);

            if ($response->failed())
            {
                return AdCampaignResult::fail('X: '.$response->body());
            }

            return AdCampaignResult::ok((string) $response->json('data.id', ''), $response->json() ?? []);
        } catch (Throwable $e)
        {
            return AdCampaignResult::fail('X: '.$e->getMessage());
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

        $response = $this->authorizedClient($connection)->get(self::BASE.'/stats/accounts/'.$connection->ad_account_id, [
            'entity' => 'CAMPAIGN',
            'entity_ids' => $campaignPlatform->external_campaign_id,
            'start_time' => $from->toIso8601String(),
            'end_time' => $to->toIso8601String(),
            'granularity' => 'DAY',
            'metric_groups' => 'ENGAGEMENT,BILLING',
        ]);

        if ($response->failed())
        {
            return [];
        }

        $series = (array) $response->json('data.0.id_data.0.metrics', []);
        $impressions = (array) ($series['impressions'] ?? []);
        $clicks = (array) ($series['clicks'] ?? []);
        $spend = (array) ($series['billed_charge_local_micro'] ?? []);

        $result = [];
        $cursor = $from->copy();
        foreach (array_keys($impressions) as $index)
        {
            $result[] = new AdMetricsDTO(
                $cursor->copy(),
                (int) ($impressions[$index] ?? 0),
                (int) ($clicks[$index] ?? 0),
                (float) (($spend[$index] ?? 0) / 1_000_000),
                0,
                ['index' => $index],
            );
            $cursor->addDay();
        }

        return $result;
    }
}
