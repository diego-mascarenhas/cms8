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

class GoogleAdsGateway extends AbstractAdPlatformGateway
{
    private const API_VERSION = 'v18';

    public function platform(): AdPlatform
    {
        return AdPlatform::GoogleAds;
    }

    protected function configKey(): string
    {
        return 'google_ads';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function scopes(): array
    {
        return ['https://www.googleapis.com/auth/adwords'];
    }

    protected function extraAuthorizationParams(): array
    {
        return [
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ];
    }

    public function isConfigured(): bool
    {
        return parent::isConfigured() && $this->developerToken() !== '';
    }

    /**
     * @return array<int, AdAccountDTO>
     */
    public function listAdAccounts(AdPlatformConnection $connection): array
    {
        $response = $this->authorizedClient($connection)
            ->withHeaders($this->developerHeaders())
            ->get('https://googleads.googleapis.com/'.self::API_VERSION.'/customers:listAccessibleCustomers');

        if ($response->failed())
        {
            return [];
        }

        $resourceNames = (array) $response->json('resourceNames', []);

        return array_map(function (string $resourceName): AdAccountDTO
        {
            $id = str_replace('customers/', '', $resourceName);

            return new AdAccountDTO($id, 'Customer '.$id);
        }, $resourceNames);
    }

    public function publish(PaidAdCampaignPlatform $campaignPlatform): AdCampaignResult
    {
        $connection = $campaignPlatform->connection;
        $campaign = $campaignPlatform->campaign;

        if ($connection === null || ! $connection->isUsable())
        {
            return AdCampaignResult::fail(__('The Google Ads connection is not ready.'));
        }

        try
        {
            $customerId = $this->normalizeCustomerId((string) $connection->ad_account_id);

            $response = $this->authorizedClient($connection)
                ->withHeaders($this->developerHeaders())
                ->post('https://googleads.googleapis.com/'.self::API_VERSION.'/customers/'.$customerId.'/campaigns:mutate', [
                    'operations' => [[
                        'create' => [
                            'name' => $campaign->name,
                            'status' => 'PAUSED',
                            'advertisingChannelType' => 'SEARCH',
                            'campaignBudget' => Arr::get($campaignPlatform->platform_payload, 'budget_resource'),
                        ],
                    ]],
                ]);

            if ($response->failed())
            {
                return AdCampaignResult::fail('Google Ads: '.$response->body());
            }

            $resourceName = (string) $response->json('results.0.resourceName', '');
            $externalId = $resourceName !== '' ? $resourceName : (string) $response->json('results.0.id', '');

            return AdCampaignResult::ok($externalId, $response->json() ?? []);
        } catch (Throwable $e)
        {
            return AdCampaignResult::fail('Google Ads: '.$e->getMessage());
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

        $customerId = $this->normalizeCustomerId((string) $connection->ad_account_id);

        $query = sprintf(
            'SELECT segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions '.
            'FROM campaign WHERE segments.date BETWEEN "%s" AND "%s"',
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        $response = $this->authorizedClient($connection)
            ->withHeaders($this->developerHeaders())
            ->post('https://googleads.googleapis.com/'.self::API_VERSION.'/customers/'.$customerId.'/googleAds:search', [
                'query' => $query,
            ]);

        if ($response->failed())
        {
            return [];
        }

        return array_map(function (array $row): AdMetricsDTO
        {
            $metrics = $row['metrics'] ?? [];

            return new AdMetricsDTO(
                Carbon::parse(Arr::get($row, 'segments.date')),
                (int) ($metrics['impressions'] ?? 0),
                (int) ($metrics['clicks'] ?? 0),
                (float) (($metrics['costMicros'] ?? 0) / 1_000_000),
                (int) round((float) ($metrics['conversions'] ?? 0)),
                $row,
            );
        }, (array) $response->json('results', []));
    }

    private function normalizeCustomerId(string $id): string
    {
        return str_replace('-', '', $id);
    }

    /**
     * @return array<string, string>
     */
    private function developerHeaders(): array
    {
        $headers = ['developer-token' => $this->developerToken()];

        $loginCustomerId = (string) ($this->credential('login_customer_id') ?? '');
        if ($loginCustomerId !== '')
        {
            $headers['login-customer-id'] = $this->normalizeCustomerId($loginCustomerId);
        }

        return $headers;
    }

    private function developerToken(): string
    {
        return (string) ($this->credential('developer_token') ?? '');
    }
}
