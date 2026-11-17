<?php

namespace App\Services\Ads;

use App\Enums\AdConnectionStatus;
use App\Enums\AdCreativeFormat;
use App\Enums\AdPlatform;
use App\Enums\PaidAdObjective;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaign;
use App\Models\PaidAdCampaignPlatform;
use App\Models\User;
use App\Services\Ads\DTO\AdAccountDTO;
use App\Services\Ads\DTO\AdCampaignResult;
use App\Services\Ads\DTO\AdMetricsDTO;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * ChatGPT ads via the OpenAI Advertiser API.
 *
 * Each Ads API key belongs to one ad account. Publishing creates a paused
 * campaign, ad group, and chat-card ad. Resume activates all three.
 */
class ChatGptAdsGateway extends AbstractAdPlatformGateway
{
    private const BASE = 'https://api.ads.openai.com/v1';

    public function platform(): AdPlatform
    {
        return AdPlatform::ChatGpt;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    protected function configKey(): string
    {
        return 'chatgpt_ads';
    }

    protected function authorizeEndpoint(): string
    {
        return self::BASE;
    }

    protected function tokenEndpoint(): string
    {
        return self::BASE;
    }

    protected function scopes(): array
    {
        return [];
    }

    public function connectWithApiKey(User $user, string $apiKey): AdPlatformConnection
    {
        $apiKey = trim($apiKey);
        $team = $user->currentTeam;

        if ($apiKey === '' || $team === null)
        {
            throw new RuntimeException(__('Paste a ChatGPT Ads API key for the current team.'));
        }

        $this->forTeam($team);

        $response = Http::withToken($apiKey)->acceptJson()->get(self::BASE.'/ad_account');

        if ($response->failed())
        {
            throw new RuntimeException(__('ChatGPT rejected that API key.'));
        }

        $account = $response->json();
        $accountId = trim((string) ($account['id'] ?? ''));

        if ($accountId === '')
        {
            throw new RuntimeException(__('ChatGPT did not return an ad account.'));
        }

        $name = (string) ($account['account_name'] ?? $account['brand_name'] ?? $account['name'] ?? $accountId);

        AdPlatformConnection::query()
            ->where('team_id', $team->id)
            ->where('platform', $this->platform())
            ->where('ad_account_id', '!=', $accountId)
            ->delete();

        return AdPlatformConnection::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'platform' => $this->platform(),
                'ad_account_id' => $accountId,
            ],
            [
                'user_id' => $user->id,
                'external_account_id' => $accountId,
                'ad_account_name' => $name,
                'access_token' => $apiKey,
                'refresh_token' => null,
                'access_token_expires_at' => null,
                'scopes' => ['ads'],
                'status' => AdConnectionStatus::Active,
                'metadata' => array_filter([
                    'currency_code' => $account['currency_code'] ?? null,
                    'timezone' => $account['timezone'] ?? null,
                    'brand_name' => $account['brand_name'] ?? null,
                    'url' => $account['url'] ?? null,
                ]),
                'last_synced_at' => now(),
            ],
        );
    }

    /**
     * @return array<int, AdAccountDTO>
     */
    public function listAdAccounts(AdPlatformConnection $connection): array
    {
        $response = $this->authorizedClient($connection)->get(self::BASE.'/ad_account');

        if ($response->failed())
        {
            return [];
        }

        $account = (array) $response->json();
        $id = (string) ($account['id'] ?? '');

        if ($id === '')
        {
            return [];
        }

        return [
            new AdAccountDTO(
                $id,
                (string) ($account['account_name'] ?? $account['name'] ?? 'ChatGPT'),
                isset($account['currency_code']) ? (string) $account['currency_code'] : null,
                isset($account['status']) ? (string) $account['status'] : null,
            ),
        ];
    }

    public function publish(PaidAdCampaignPlatform $campaignPlatform): AdCampaignResult
    {
        $connection = $campaignPlatform->connection;
        $campaign = $campaignPlatform->campaign;

        if ($connection === null || ! $connection->isUsable())
        {
            return AdCampaignResult::fail(__('The ChatGPT connection is not ready.'));
        }

        if ($campaign === null)
        {
            return AdCampaignResult::fail(__('The campaign is missing.'));
        }

        try
        {
            $state = (array) data_get($campaignPlatform->platform_payload, 'chatgpt', []);
            [$title, $body, $url] = $this->creativeCopy($campaign);
            $micros = $this->lifetimeMicros($campaign);
            $bidding = $this->bidding($campaign);

            if ($micros < 1_000_000)
            {
                return AdCampaignResult::fail(__('ChatGPT needs a lifetime budget of at least 1 in the account currency.'));
            }

            $fileId = (string) ($state['file_id'] ?? '');
            if ($fileId === '')
            {
                $fileId = $this->uploadImage($connection, $campaign);
                $state['file_id'] = $fileId;
                $this->remember($campaignPlatform, $state);
            }

            $campaignId = (string) ($state['campaign_id'] ?? $campaignPlatform->external_campaign_id ?? '');
            if ($campaignId === '')
            {
                $created = $this->post($connection, '/campaigns', $this->campaignPayload($campaign, $micros, $bidding), 'chatgpt-campaign-'.$campaignPlatform->id);
                $campaignId = (string) ($created['id'] ?? '');
                if ($campaignId === '')
                {
                    return AdCampaignResult::fail(__('ChatGPT did not return a campaign id.'));
                }
                $state['campaign_id'] = $campaignId;
                $this->remember($campaignPlatform, $state);
            }

            $adGroupId = (string) ($state['ad_group_id'] ?? '');
            if ($adGroupId === '')
            {
                $created = $this->post($connection, '/ad_groups', $this->adGroupPayload($campaign, $campaignId, $bidding), 'chatgpt-ad-group-'.$campaignPlatform->id);
                $adGroupId = (string) ($created['id'] ?? '');
                if ($adGroupId === '')
                {
                    return AdCampaignResult::fail(__('ChatGPT did not return an ad group id.'));
                }
                $state['ad_group_id'] = $adGroupId;
                $this->remember($campaignPlatform, $state);
            }

            $adId = (string) ($state['ad_id'] ?? '');
            if ($adId === '')
            {
                $created = $this->post($connection, '/ads', [
                    'ad_group_id' => $adGroupId,
                    'name' => $this->boundedName($campaign->name.' card'),
                    'status' => 'paused',
                    'creative' => [
                        'type' => 'chat_card',
                        'title' => $title,
                        'body' => $body,
                        'target_url' => $url,
                        'file_id' => $fileId,
                    ],
                ], 'chatgpt-ad-'.$campaignPlatform->id);
                $adId = (string) ($created['id'] ?? '');
                if ($adId === '')
                {
                    return AdCampaignResult::fail(__('ChatGPT did not return an ad id.'));
                }
                $state['ad_id'] = $adId;
                $state['review_status'] = $created['review_status'] ?? null;
                $this->remember($campaignPlatform, $state);
            }

            if (empty($state['activated']))
            {
                $this->transition($campaignPlatform->fresh() ?? $campaignPlatform, 'activate');
                $state['activated'] = true;
                $this->remember($campaignPlatform, $state);
            }

            return AdCampaignResult::ok($campaignId, ['chatgpt' => $state]);
        } catch (Throwable $e)
        {
            return AdCampaignResult::fail($e->getMessage());
        }
    }

    public function pause(PaidAdCampaignPlatform $campaignPlatform): void
    {
        $this->transition($campaignPlatform, 'pause');
    }

    public function resume(PaidAdCampaignPlatform $campaignPlatform): void
    {
        $this->transition($campaignPlatform, 'activate');
    }

    /**
     * @return array<int, AdMetricsDTO>
     */
    public function getMetrics(PaidAdCampaignPlatform $campaignPlatform, CarbonInterface $from, CarbonInterface $to): array
    {
        $connection = $campaignPlatform->connection;
        $campaignId = $campaignPlatform->external_campaign_id;

        if ($connection === null || $campaignId === null || $campaignId === '')
        {
            return [];
        }

        $range = json_encode([
            'type' => 'date_range',
            'since' => $from->format('Y-m-d'),
            'until' => $to->format('Y-m-d'),
            'timezone' => 'UTC',
        ]);

        $query = http_build_query([
            'time_granularity' => 'daily',
            'limit' => 100,
        ]);
        $query .= '&'.rawurlencode('time_ranges[]').'='.rawurlencode((string) $range);

        $response = $this->authorizedClient($connection)->get(self::BASE.'/campaigns/'.$campaignId.'/insights?'.$query);

        if ($response->failed())
        {
            return [];
        }

        $metrics = [];

        foreach ((array) $response->json('data', []) as $row)
        {
            if (! is_array($row))
            {
                continue;
            }

            $date = $row['readable_time'] ?? null;
            if (! is_string($date) || $date === '')
            {
                $date = isset($row['start_time']) ? Carbon::createFromTimestamp((int) $row['start_time']) : null;
            }

            if ($date === null)
            {
                continue;
            }

            $metrics[] = new AdMetricsDTO(
                Carbon::parse($date),
                (int) ($row['impressions'] ?? 0),
                (int) ($row['clicks'] ?? 0),
                (float) ($row['spend'] ?? 0),
                (int) ($row['conversions'] ?? 0),
                $row,
            );
        }

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(AdPlatformConnection $connection, string $path, array $payload, string $idempotencyKey): array
    {
        $response = $this->authorizedClient($connection)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post(self::BASE.$path, $payload);

        if ($response->failed())
        {
            throw new RuntimeException($this->apiError($response->body()));
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function remember(PaidAdCampaignPlatform $campaignPlatform, array $state): void
    {
        $campaignPlatform->forceFill([
            'platform_payload' => array_merge((array) $campaignPlatform->platform_payload, [
                'chatgpt' => $state,
            ]),
            'external_campaign_id' => $state['campaign_id'] ?? $campaignPlatform->external_campaign_id,
        ])->save();
    }

    /**
     * @param  array{bidding_type: string, billing_event_type: string, max_bid_micros: int}  $bidding
     * @return array<string, mixed>
     */
    private function campaignPayload(PaidAdCampaign $campaign, int $micros, array $bidding): array
    {
        $payload = [
            'name' => $this->boundedName($campaign->name),
            'status' => 'paused',
            'budget' => [
                'lifetime_spend_limit_micros' => $micros,
            ],
            'bidding_type' => $bidding['bidding_type'],
        ];

        if ($campaign->start_at !== null)
        {
            $payload['start_time'] = $campaign->start_at->getTimestamp();
        }

        if ($campaign->end_at !== null)
        {
            $payload['end_time'] = $campaign->end_at->getTimestamp();
        }

        return $payload;
    }

    /**
     * @param  array{bidding_type: string, billing_event_type: string, max_bid_micros: int}  $bidding
     * @return array<string, mixed>
     */
    private function adGroupPayload(PaidAdCampaign $campaign, string $campaignId, array $bidding): array
    {
        $payload = [
            'campaign_id' => $campaignId,
            'name' => $this->boundedName($campaign->name.' ads'),
            'status' => 'paused',
            'bidding_config' => [
                'billing_event_type' => $bidding['billing_event_type'],
                'max_bid_micros' => $bidding['max_bid_micros'],
            ],
        ];

        $hints = $this->contextHints($campaign);
        if ($hints !== [])
        {
            $payload['context_hints'] = $hints;
        }

        return $payload;
    }

    /**
     * Awareness bids per impression. Every other objective bids per click.
     * Conversion bidding needs an event setting that this account may not have yet.
     *
     * @return array{bidding_type: string, billing_event_type: string, max_bid_micros: int}
     */
    private function bidding(PaidAdCampaign $campaign): array
    {
        if ($campaign->objective === PaidAdObjective::Awareness)
        {
            return [
                'bidding_type' => 'impressions',
                'billing_event_type' => 'impression',
                'max_bid_micros' => 50_000,
            ];
        }

        return [
            'bidding_type' => 'clicks',
            'billing_event_type' => 'click',
            'max_bid_micros' => 1_000_000,
        ];
    }

    private function lifetimeMicros(PaidAdCampaign $campaign): int
    {
        $micros = (int) round(((float) ($campaign->budget_amount ?? 0)) * 1_000_000);

        if (($campaign->budget_type ?? 'lifetime') !== 'daily')
        {
            return $micros;
        }

        $days = 30;
        if ($campaign->start_at !== null && $campaign->end_at !== null && $campaign->end_at->greaterThan($campaign->start_at))
        {
            $days = max(1, (int) $campaign->start_at->diffInDays($campaign->end_at));
        }

        return $micros * $days;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function creativeCopy(PaidAdCampaign $campaign): array
    {
        $title = trim((string) Arr::get($campaign->creative, 'headline', ''));
        if (mb_strlen($title) < 3)
        {
            $title = trim((string) $campaign->name);
        }

        $title = mb_substr($title, 0, 50);
        if (mb_strlen(trim($title)) < 3)
        {
            throw new RuntimeException(__('ChatGPT needs a title of at least 3 characters.'));
        }

        $body = trim((string) Arr::get($campaign->creative, 'body', ''));
        if ($body === '')
        {
            $body = $title;
        }

        $url = trim((string) Arr::get($campaign->creative, 'url', ''));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false || strlen($url) > 2048)
        {
            throw new RuntimeException(__('Add a valid destination URL before publishing to ChatGPT.'));
        }

        return [$title, mb_substr($body, 0, 100), $url];
    }

    private function uploadImage(AdPlatformConnection $connection, PaidAdCampaign $campaign): string
    {
        $asset = $this->squareAsset($campaign);
        if ($asset === null)
        {
            throw new RuntimeException(__('Add a square image before publishing to ChatGPT.'));
        }

        $path = (string) ($asset['path'] ?? '');
        $client = $this->authorizedClient($connection);

        if ($path !== '' && Storage::disk('public')->exists($path))
        {
            $absolute = Storage::disk('public')->path($path);
            $response = $client->attach('file', fopen($absolute, 'r'), basename($absolute))
                ->post(self::BASE.'/upload');
        } else
        {
            $imageUrl = trim((string) ($asset['url'] ?? ''));
            if (filter_var($imageUrl, FILTER_VALIDATE_URL) === false)
            {
                throw new RuntimeException(__('Add a square image before publishing to ChatGPT.'));
            }

            $response = $client->post(self::BASE.'/upload', [
                'image_url' => $imageUrl,
            ]);
        }

        if ($response->failed())
        {
            throw new RuntimeException($this->apiError($response->body()));
        }

        $fileId = (string) ($response->json('file_id') ?: $response->json('id') ?: '');
        if ($fileId === '')
        {
            throw new RuntimeException(__('ChatGPT did not return an image id.'));
        }

        return $fileId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function squareAsset(PaidAdCampaign $campaign): ?array
    {
        $assets = Arr::get($campaign->creative, 'assets', []);
        if (! is_array($assets))
        {
            return null;
        }

        $fallback = null;

        foreach ($assets as $asset)
        {
            if (! is_array($asset) || $this->isVideoAsset($asset))
            {
                continue;
            }

            $fallback ??= $asset;
            if (($asset['format'] ?? '') === AdCreativeFormat::Square->value)
            {
                return $asset;
            }
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $asset
     */
    private function isVideoAsset(array $asset): bool
    {
        if (($asset['media'] ?? '') === 'video')
        {
            return true;
        }

        $path = strtolower((string) ($asset['path'] ?? $asset['url'] ?? ''));

        return str_ends_with(strtok($path, '?') ?: $path, '.mp4');
    }

    /**
     * @return array<int, string>
     */
    private function contextHints(PaidAdCampaign $campaign): array
    {
        $raw = (string) Arr::get($campaign->targeting, 'interests', '');
        $parts = preg_split('/[,;\n]+/', $raw) ?: [];

        $hints = [];
        foreach ($parts as $part)
        {
            $hint = mb_substr(trim((string) $part), 0, 200);
            if ($hint !== '')
            {
                $hints[] = $hint;
            }
        }

        return array_slice($hints, 0, 8);
    }

    private function boundedName(string $name): string
    {
        $name = trim($name);
        if (mb_strlen($name) < 3)
        {
            $name = 'ChatGPT campaign';
        }

        return mb_substr($name, 0, 1000);
    }

    private function transition(PaidAdCampaignPlatform $campaignPlatform, string $action): void
    {
        $connection = $campaignPlatform->connection;
        if ($connection === null || ! $connection->isUsable())
        {
            throw new RuntimeException(__('The ChatGPT connection is not ready.'));
        }

        $state = (array) data_get($campaignPlatform->platform_payload, 'chatgpt', []);
        $targets = array_filter([
            'campaigns' => $campaignPlatform->external_campaign_id,
            'ad_groups' => $state['ad_group_id'] ?? null,
            'ads' => $state['ad_id'] ?? null,
        ]);

        foreach ($targets as $collection => $id)
        {
            $response = $this->authorizedClient($connection)->post(self::BASE.'/'.$collection.'/'.$id.'/'.$action);

            if ($response->failed())
            {
                throw new RuntimeException($this->apiError($response->body()));
            }
        }
    }

    private function apiError(string $body): string
    {
        $body = trim($body);
        if (strlen($body) > 400)
        {
            $body = substr($body, 0, 400);
        }

        return 'ChatGPT: '.$body;
    }
}
