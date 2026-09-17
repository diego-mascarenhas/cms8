<?php

namespace App\Services\Ads;

use App\Enums\AdPlatform;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaign;
use App\Models\PaidAdCampaignPlatform;
use App\Services\Ads\DTO\AdAccountDTO;
use App\Services\Ads\DTO\AdCampaignResult;
use App\Services\Ads\DTO\AdMetricsDTO;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Throwable;

class GoogleAdsGateway extends AbstractAdPlatformGateway
{
    private const API_VERSION = 'v18';

    private const LANGUAGE_SPANISH = 'languageConstants/1003';

    /**
     * @var array<string, string>
     */
    private const GEO_TARGETS = [
        'argentina' => 'geoTargetConstants/2032',
        'ar' => 'geoTargetConstants/2032',
        'mexico' => 'geoTargetConstants/2124',
        'méxico' => 'geoTargetConstants/2124',
        'mx' => 'geoTargetConstants/2124',
        'colombia' => 'geoTargetConstants/2151',
        'co' => 'geoTargetConstants/2151',
        'chile' => 'geoTargetConstants/2152',
        'cl' => 'geoTargetConstants/2152',
        'peru' => 'geoTargetConstants/2604',
        'perú' => 'geoTargetConstants/2604',
        'pe' => 'geoTargetConstants/2604',
        'uruguay' => 'geoTargetConstants/2468',
        'uy' => 'geoTargetConstants/2468',
        'paraguay' => 'geoTargetConstants/2600',
        'py' => 'geoTargetConstants/2600',
        'bolivia' => 'geoTargetConstants/2068',
        'bo' => 'geoTargetConstants/2068',
        'ecuador' => 'geoTargetConstants/2218',
        'ec' => 'geoTargetConstants/2218',
        'espana' => 'geoTargetConstants/2272',
        'españa' => 'geoTargetConstants/2272',
        'spain' => 'geoTargetConstants/2272',
        'es' => 'geoTargetConstants/2272',
        'latam' => 'geoTargetConstants/2032',
        'latinoamerica' => 'geoTargetConstants/2032',
        'latinoamérica' => 'geoTargetConstants/2032',
    ];

    /**
     * @var array<int, string>
     */
    private const FALLBACK_KEYWORDS = [
        'tienda online whatsapp',
        'catalogo digital',
        'menu digital qr',
    ];

    /**
     * @var array<int, string>
     */
    private const NEGATIVE_KEYWORDS = [
        'gratis',
        'apk',
        'empleo',
        'curso',
    ];

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

        return array_values(array_filter(array_map(function (string $resourceName) use ($connection): ?AdAccountDTO
        {
            $id = str_replace('customers/', '', $resourceName);

            return $this->describeCustomer($connection, $id);
        }, $resourceNames)));
    }

    public function publish(PaidAdCampaignPlatform $campaignPlatform): AdCampaignResult
    {
        $connection = $campaignPlatform->connection;
        $campaign = $campaignPlatform->campaign;

        if ($connection === null || ! $connection->isUsable())
        {
            return AdCampaignResult::fail(__('The Google Ads connection is not ready.'));
        }

        if ($campaign === null)
        {
            return AdCampaignResult::fail(__('The campaign is missing.'));
        }

        $finalUrl = trim((string) Arr::get($campaign->creative, 'url', ''));
        if ($finalUrl === '' || filter_var($finalUrl, FILTER_VALIDATE_URL) === false)
        {
            return AdCampaignResult::fail(__('Add a valid destination URL before publishing to Google Ads.'));
        }

        $budgetMicros = $this->budgetMicros($campaign);
        if ($budgetMicros < 1_000_000)
        {
            return AdCampaignResult::fail(__('Google Ads needs a daily budget of at least 1 in the account currency.'));
        }

        try
        {
            $customerId = $this->normalizeCustomerId((string) $connection->ad_account_id);
            $operations = $this->searchCampaignOperations($campaign, $customerId, $budgetMicros, $finalUrl);

            $response = $this->mutate($connection, $customerId, $operations);

            if ($response->failed())
            {
                return AdCampaignResult::fail('Google Ads: '.$response->body());
            }

            $resourceName = $this->campaignResourceNameFromMutate($response->json() ?? []);
            if ($resourceName === '')
            {
                return AdCampaignResult::fail(__('Google Ads did not return a campaign id.'));
            }

            return AdCampaignResult::ok($resourceName, $response->json() ?? []);
        } catch (Throwable $e)
        {
            return AdCampaignResult::fail('Google Ads: '.$e->getMessage());
        }
    }

    public function pause(PaidAdCampaignPlatform $campaignPlatform): void
    {
        $this->setCampaignStatus($campaignPlatform, 'PAUSED');
    }

    public function resume(PaidAdCampaignPlatform $campaignPlatform): void
    {
        $this->setCampaignStatus($campaignPlatform, 'ENABLED');
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
        $resourceName = $this->campaignResourceName($campaignPlatform, $customerId);

        $query = sprintf(
            'SELECT segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions '.
            'FROM campaign WHERE campaign.resource_name = "%s" AND segments.date BETWEEN "%s" AND "%s"',
            $resourceName,
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

    private function describeCustomer(AdPlatformConnection $connection, string $id): AdAccountDTO
    {
        $response = $this->authorizedClient($connection)
            ->withHeaders($this->developerHeaders())
            ->post('https://googleads.googleapis.com/'.self::API_VERSION.'/customers/'.$id.'/googleAds:search', [
                'query' => 'SELECT customer.id, customer.descriptive_name, customer.currency_code, customer.status FROM customer LIMIT 1',
            ]);

        if ($response->failed())
        {
            return new AdAccountDTO($id, 'Customer '.$id);
        }

        $customer = (array) $response->json('results.0.customer', []);

        return new AdAccountDTO(
            $id,
            (string) ($customer['descriptiveName'] ?? 'Customer '.$id),
            $customer['currencyCode'] ?? null,
            isset($customer['status']) ? (string) $customer['status'] : null,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchCampaignOperations(PaidAdCampaign $campaign, string $customerId, int $budgetMicros, string $finalUrl): array
    {
        $budgetName = $customerId.'/campaignBudgets/-1';
        $campaignName = $customerId.'/campaigns/-2';
        $adGroupName = $customerId.'/adGroups/-3';
        $suffix = now()->format('Ymd-His');

        $operations = [
            [
                'campaignBudgetOperation' => [
                    'create' => [
                        'resourceName' => 'customers/'.$budgetName,
                        'name' => $campaign->name.' budget '.$suffix,
                        'deliveryMethod' => 'STANDARD',
                        'amountMicros' => $budgetMicros,
                        'explicitlyShared' => false,
                    ],
                ],
            ],
            [
                'campaignOperation' => [
                    'create' => [
                        'resourceName' => 'customers/'.$campaignName,
                        'name' => $campaign->name,
                        'status' => 'PAUSED',
                        'advertisingChannelType' => 'SEARCH',
                        'campaignBudget' => 'customers/'.$budgetName,
                        'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
                        'targetSpend' => new \stdClass,
                        'networkSettings' => [
                            'targetGoogleSearch' => true,
                            'targetSearchNetwork' => false,
                            'targetContentNetwork' => false,
                            'targetPartnerSearchNetwork' => false,
                        ],
                        'geoTargetTypeSetting' => [
                            'positiveGeoTargetType' => 'PRESENCE',
                            'negativeGeoTargetType' => 'PRESENCE',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($this->geoTargetConstants($campaign) as $geoTarget)
        {
            $operations[] = [
                'campaignCriterionOperation' => [
                    'create' => [
                        'campaign' => 'customers/'.$campaignName,
                        'location' => [
                            'geoTargetConstant' => $geoTarget,
                        ],
                    ],
                ],
            ];
        }

        $operations[] = [
            'campaignCriterionOperation' => [
                'create' => [
                    'campaign' => 'customers/'.$campaignName,
                    'language' => [
                        'languageConstant' => self::LANGUAGE_SPANISH,
                    ],
                ],
            ],
        ];

        $operations[] = [
            'adGroupOperation' => [
                'create' => [
                    'resourceName' => 'customers/'.$adGroupName,
                    'campaign' => 'customers/'.$campaignName,
                    'name' => $campaign->name.' · Search',
                    'status' => 'ENABLED',
                    'type' => 'SEARCH_STANDARD',
                ],
            ],
        ];

        foreach ($this->keywords($campaign) as $keyword)
        {
            $operations[] = [
                'adGroupCriterionOperation' => [
                    'create' => [
                        'adGroup' => 'customers/'.$adGroupName,
                        'status' => 'ENABLED',
                        'keyword' => [
                            'text' => $keyword,
                            'matchType' => 'PHRASE',
                        ],
                    ],
                ],
            ];
        }

        foreach (self::NEGATIVE_KEYWORDS as $keyword)
        {
            $operations[] = [
                'adGroupCriterionOperation' => [
                    'create' => [
                        'adGroup' => 'customers/'.$adGroupName,
                        'negative' => true,
                        'keyword' => [
                            'text' => $keyword,
                            'matchType' => 'BROAD',
                        ],
                    ],
                ],
            ];
        }

        $operations[] = [
            'adGroupAdOperation' => [
                'create' => [
                    'adGroup' => 'customers/'.$adGroupName,
                    'status' => 'ENABLED',
                    'ad' => [
                        'finalUrls' => [$finalUrl],
                        'responsiveSearchAd' => [
                            'headlines' => $this->headlineAssets($campaign),
                            'descriptions' => $this->descriptionAssets($campaign),
                            'path1' => 'tienda',
                            'path2' => 'online',
                        ],
                    ],
                ],
            ],
        ];

        return $operations;
    }

    /**
     * @param  array<int, array<string, mixed>>  $operations
     */
    private function mutate(AdPlatformConnection $connection, string $customerId, array $operations): Response
    {
        return $this->authorizedClient($connection)
            ->withHeaders($this->developerHeaders())
            ->post('https://googleads.googleapis.com/'.self::API_VERSION.'/customers/'.$customerId.'/googleAds:mutate', [
                'mutateOperations' => $operations,
            ]);
    }

    private function setCampaignStatus(PaidAdCampaignPlatform $campaignPlatform, string $status): void
    {
        $connection = $campaignPlatform->connection;

        if ($connection === null || $campaignPlatform->external_campaign_id === null)
        {
            return;
        }

        $customerId = $this->normalizeCustomerId((string) $connection->ad_account_id);
        $resourceName = $this->campaignResourceName($campaignPlatform, $customerId);

        $this->authorizedClient($connection)
            ->withHeaders($this->developerHeaders())
            ->post('https://googleads.googleapis.com/'.self::API_VERSION.'/customers/'.$customerId.'/campaigns:mutate', [
                'operations' => [[
                    'update' => [
                        'resourceName' => $resourceName,
                        'status' => $status,
                    ],
                    'updateMask' => 'status',
                ]],
            ]);
    }

    private function budgetMicros(PaidAdCampaign $campaign): int
    {
        return (int) round(((float) $campaign->budget_amount) * 1_000_000);
    }

    /**
     * @return array<int, string>
     */
    private function geoTargetConstants(PaidAdCampaign $campaign): array
    {
        $raw = strtolower((string) Arr::get($campaign->targeting, 'locations', ''));
        $parts = preg_split('/[,;\/|]+/u', $raw) ?: [];
        $targets = [];

        foreach ($parts as $part)
        {
            $key = trim($part);
            if ($key === '')
            {
                continue;
            }

            $mapped = self::GEO_TARGETS[$key] ?? null;
            if ($mapped !== null)
            {
                $targets[$mapped] = $mapped;
            }
        }

        if ($targets === [])
        {
            $targets[self::GEO_TARGETS['argentina']] = self::GEO_TARGETS['argentina'];
        }

        return array_values($targets);
    }

    /**
     * @return array<int, string>
     */
    private function keywords(PaidAdCampaign $campaign): array
    {
        $raw = (string) Arr::get($campaign->targeting, 'interests', '');
        $parts = preg_split('/[,;\n]+/u', $raw) ?: [];
        $keywords = [];

        foreach ($parts as $part)
        {
            $keyword = mb_substr(trim($part), 0, 80);
            if ($keyword !== '')
            {
                $keywords[$keyword] = $keyword;
            }
        }

        $headline = trim((string) Arr::get($campaign->creative, 'headline', ''));
        if ($keywords === [] && $headline !== '')
        {
            $keywords[$headline] = mb_substr($headline, 0, 80);
        }

        if ($keywords === [])
        {
            foreach (self::FALLBACK_KEYWORDS as $keyword)
            {
                $keywords[$keyword] = $keyword;
            }
        }

        return array_values($keywords);
    }

    /**
     * @return array<int, array{text: string}>
     */
    private function headlineAssets(PaidAdCampaign $campaign): array
    {
        $candidates = [
            (string) Arr::get($campaign->creative, 'headline', ''),
            (string) $campaign->name,
            'Pedidos por WhatsApp',
            'Creá tu tienda online',
            'Sin comisión por venta',
            'Empezá hoy',
        ];

        return $this->textAssets($candidates, 30, 3);
    }

    /**
     * @return array<int, array{text: string}>
     */
    private function descriptionAssets(PaidAdCampaign $campaign): array
    {
        $candidates = [
            (string) Arr::get($campaign->creative, 'body', ''),
            'Tus clientes piden desde el celular y vos recibís el pedido por WhatsApp.',
            'Catálogo, QR y abono fijo. Sin comisión por venta.',
        ];

        return $this->textAssets($candidates, 90, 2);
    }

    /**
     * @param  array<int, string>  $candidates
     * @return array<int, array{text: string}>
     */
    private function textAssets(array $candidates, int $maxLength, int $minimum): array
    {
        $unique = [];

        foreach ($candidates as $candidate)
        {
            $text = trim(preg_replace('/\s+/u', ' ', $candidate) ?? '');
            if ($text === '')
            {
                continue;
            }

            $text = mb_substr($text, 0, $maxLength);
            $unique[$text] = ['text' => $text];

            if (count($unique) >= 15)
            {
                break;
            }
        }

        $fallbacks = $maxLength <= 30
            ? ['Empezá hoy', 'Conocé más', 'Más información']
            : ['Entrá y conocé cómo funciona.', 'Empezá en minutos, sin instalar nada.'];

        foreach ($fallbacks as $fallback)
        {
            if (count($unique) >= $minimum)
            {
                break;
            }

            $unique[$fallback] = ['text' => $fallback];
        }

        return array_values($unique);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function campaignResourceNameFromMutate(array $payload): string
    {
        foreach ((array) Arr::get($payload, 'mutateOperationResponses', []) as $item)
        {
            $resourceName = (string) Arr::get($item, 'campaignResult.resourceName', '');
            if ($resourceName !== '')
            {
                return $resourceName;
            }
        }

        return (string) Arr::get($payload, 'results.0.resourceName', '');
    }

    private function campaignResourceName(PaidAdCampaignPlatform $campaignPlatform, string $customerId): string
    {
        $id = (string) $campaignPlatform->external_campaign_id;

        if (str_contains($id, 'campaigns/'))
        {
            return $id;
        }

        return 'customers/'.$customerId.'/campaigns/'.$id;
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
