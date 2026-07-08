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
use Illuminate\Http\Client\PendingRequest;
use Throwable;

class LinkedInAdsGateway extends AbstractAdPlatformGateway
{
    private const REST_VERSION = '202410';

    public function platform(): AdPlatform
    {
        return AdPlatform::LinkedIn;
    }

    protected function configKey(): string
    {
        return 'linkedin_ads';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://www.linkedin.com/oauth/v2/accessToken';
    }

    protected function scopes(): array
    {
        return ['r_ads', 'rw_ads', 'r_ads_reporting'];
    }

    /**
     * @return array<int, AdAccountDTO>
     */
    public function listAdAccounts(AdPlatformConnection $connection): array
    {
        $response = $this->linkedInClient($connection)
            ->get('https://api.linkedin.com/rest/adAccounts', ['q' => 'search']);

        if ($response->failed())
        {
            return [];
        }

        return array_map(function (array $row): AdAccountDTO
        {
            return new AdAccountDTO(
                (string) ($row['id'] ?? ''),
                (string) ($row['name'] ?? 'Account'),
                $row['currency'] ?? null,
                $row['status'] ?? null,
            );
        }, (array) $response->json('elements', []));
    }

    public function publish(PaidAdCampaignPlatform $campaignPlatform): AdCampaignResult
    {
        $connection = $campaignPlatform->connection;
        $campaign = $campaignPlatform->campaign;

        if ($connection === null || ! $connection->isUsable())
        {
            return AdCampaignResult::fail(__('The LinkedIn connection is not ready.'));
        }

        try
        {
            $response = $this->linkedInClient($connection)->post('https://api.linkedin.com/rest/adCampaignGroups', [
                'account' => 'urn:li:sponsoredAccount:'.$connection->ad_account_id,
                'name' => $campaign->name,
                'status' => 'DRAFT',
            ]);

            if ($response->failed())
            {
                return AdCampaignResult::fail('LinkedIn: '.$response->body());
            }

            $externalId = (string) ($response->header('x-linkedin-id') ?: $response->json('id', ''));

            return AdCampaignResult::ok($externalId, $response->json() ?? []);
        } catch (Throwable $e)
        {
            return AdCampaignResult::fail('LinkedIn: '.$e->getMessage());
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

        $response = $this->linkedInClient($connection)->get('https://api.linkedin.com/rest/adAnalytics', [
            'q' => 'analytics',
            'pivot' => 'CAMPAIGN_GROUP',
            'timeGranularity' => 'DAILY',
            'campaigns[0]' => 'urn:li:sponsoredCampaignGroup:'.$campaignPlatform->external_campaign_id,
            'dateRange.start.day' => $from->day,
            'dateRange.start.month' => $from->month,
            'dateRange.start.year' => $from->year,
            'dateRange.end.day' => $to->day,
            'dateRange.end.month' => $to->month,
            'dateRange.end.year' => $to->year,
        ]);

        if ($response->failed())
        {
            return [];
        }

        return array_map(function (array $row): AdMetricsDTO
        {
            $day = $row['dateRange']['start'] ?? [];
            $date = isset($day['year'])
                ? Carbon::create((int) $day['year'], (int) $day['month'], (int) $day['day'])
                : now();

            return new AdMetricsDTO(
                $date,
                (int) ($row['impressions'] ?? 0),
                (int) ($row['clicks'] ?? 0),
                (float) ($row['costInLocalCurrency'] ?? 0),
                (int) ($row['externalWebsiteConversions'] ?? 0),
                $row,
            );
        }, (array) $response->json('elements', []));
    }

    private function linkedInClient(AdPlatformConnection $connection): PendingRequest
    {
        return $this->authorizedClient($connection)->withHeaders([
            'LinkedIn-Version' => self::REST_VERSION,
            'X-Restli-Protocol-Version' => '2.0.0',
        ]);
    }
}
