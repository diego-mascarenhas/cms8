<?php

namespace Tests\Unit;

use App\Enums\AdPlatform;
use App\Models\AdPlatformConnection;
use App\Models\PaidAdCampaign;
use App\Models\PaidAdCampaignPlatform;
use App\Models\Team;
use App\Services\Ads\GoogleAdsGateway;
use App\Services\Ads\MetaAdsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdPlatformGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_settings_credentials_make_platform_configured(): void
    {
        config(['services.meta_ads.app_id' => '', 'services.meta_ads.app_secret' => '']);

        $team = Team::factory()->create();

        $gateway = app(MetaAdsGateway::class)->forTeam($team);
        $this->assertFalse($gateway->isConfigured());

        $team->setSetting('paid_ads_meta_app_id', 'team-app-id', ['group' => 'paid_ads', 'is_encrypted' => false]);
        $team->setSetting('paid_ads_meta_app_secret', 'team-app-secret', ['group' => 'paid_ads', 'is_encrypted' => true]);
        $team->refresh();

        $gateway = app(MetaAdsGateway::class)->forTeam($team->fresh());
        $this->assertTrue($gateway->isConfigured());

        $url = $gateway->buildAuthorizationUrl(new \App\Models\User(['id' => 1]));
        $this->assertStringContainsString('client_id=team-app-id', $url);
    }

    public function test_team_credentials_override_global_config(): void
    {
        config(['services.google_ads.client_id' => 'global-id', 'services.google_ads.client_secret' => 'global-secret', 'services.google_ads.developer_token' => 'global-dev']);

        $team = Team::factory()->create();
        $team->setSetting('paid_ads_google_client_id', 'team-id', ['group' => 'paid_ads', 'is_encrypted' => false]);
        $team->refresh();

        $gateway = app(GoogleAdsGateway::class)->forTeam($team->fresh());
        $url = $gateway->buildAuthorizationUrl(new \App\Models\User(['id' => 1]));

        $this->assertStringContainsString('client_id=team-id', $url);
    }

    public function test_meta_gateway_lists_ad_accounts(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [
                    ['id' => 'act_1', 'name' => 'Brand', 'currency' => 'EUR', 'account_status' => 1],
                    ['id' => 'act_2', 'name' => 'Growth', 'currency' => 'USD', 'account_status' => 1],
                ],
            ], 200),
        ]);

        $connection = AdPlatformConnection::factory()->create(['platform' => AdPlatform::Meta]);

        $accounts = app(MetaAdsGateway::class)->listAdAccounts($connection);

        $this->assertCount(2, $accounts);
        $this->assertSame('act_1', $accounts[0]->id);
        $this->assertSame('Brand', $accounts[0]->name);
        $this->assertSame('EUR', $accounts[0]->currency);
    }

    public function test_google_gateway_maps_metrics(): void
    {
        Http::fake([
            'googleads.googleapis.com/*' => Http::response([
                'results' => [[
                    'segments' => ['date' => '2026-07-01'],
                    'metrics' => ['impressions' => 500, 'clicks' => 25, 'costMicros' => 5_000_000, 'conversions' => 4],
                ]],
            ], 200),
        ]);

        $campaign = PaidAdCampaign::factory()->create();
        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $campaign->team_id,
            'platform' => AdPlatform::GoogleAds,
        ]);
        $campaignPlatform = PaidAdCampaignPlatform::factory()->published('customers/1/campaigns/2')->create([
            'paid_ad_campaign_id' => $campaign->id,
            'ad_platform_connection_id' => $connection->id,
            'platform' => AdPlatform::GoogleAds,
        ]);

        $metrics = app(GoogleAdsGateway::class)->getMetrics(
            $campaignPlatform,
            now()->subDays(7),
            now(),
        );

        $this->assertCount(1, $metrics);
        $this->assertSame(500, $metrics[0]->impressions);
        $this->assertSame(25, $metrics[0]->clicks);
        $this->assertSame(5.0, $metrics[0]->spend);
        $this->assertSame(4, $metrics[0]->conversions);
    }

    public function test_google_gateway_publishes_search_campaign_for_argentina(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request)
        {
            if (str_contains($request->url(), 'googleAds:mutate'))
            {
                return Http::response([
                    'mutateOperationResponses' => [
                        ['campaignBudgetResult' => ['resourceName' => 'customers/123/campaignBudgets/1']],
                        ['campaignResult' => ['resourceName' => 'customers/123/campaigns/456']],
                    ],
                ], 200);
            }

            return Http::response(['results' => []], 200);
        });

        $campaign = PaidAdCampaign::factory()->create([
            'name' => 'PF | Search | AR | Tienda WhatsApp',
            'budget_amount' => 10,
            'currency' => 'EUR',
            'targeting' => [
                'locations' => 'Argentina',
                'interests' => 'tienda online whatsapp, menu digital qr',
            ],
            'creative' => [
                'headline' => 'Pedidos por WhatsApp',
                'body' => 'Creá tu tienda y recibí pedidos por WhatsApp. Sin comisión por venta.',
                'url' => 'https://pedimosfacil.com/register',
            ],
        ]);
        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $campaign->team_id,
            'platform' => AdPlatform::GoogleAds,
            'ad_account_id' => '123-456-7890',
        ]);
        $campaignPlatform = PaidAdCampaignPlatform::factory()->create([
            'paid_ad_campaign_id' => $campaign->id,
            'ad_platform_connection_id' => $connection->id,
            'platform' => AdPlatform::GoogleAds,
        ]);

        $result = app(GoogleAdsGateway::class)->publish($campaignPlatform->load(['connection', 'campaign']));

        $this->assertTrue($result->success);
        $this->assertSame('customers/123/campaigns/456', $result->externalCampaignId);

        $mutate = collect(Http::recorded())
            ->first(fn (array $pair) => str_contains($pair[0]->url(), 'googleAds:mutate'));

        $this->assertNotNull($mutate);
        $payload = $mutate[0]->data();

        $this->assertSame(10_000_000, data_get($payload, 'mutateOperations.0.campaignBudgetOperation.create.amountMicros'));
        $this->assertSame(
            'geoTargetConstants/2032',
            data_get($payload, 'mutateOperations.2.campaignCriterionOperation.create.location.geoTargetConstant'),
        );
        $this->assertSame(
            'tienda online whatsapp',
            data_get($payload, 'mutateOperations.5.adGroupCriterionOperation.create.keyword.text'),
        );
        $this->assertSame(
            ['https://pedimosfacil.com/register'],
            data_get($payload, 'mutateOperations.11.adGroupAdOperation.create.ad.finalUrls'),
        );
        $this->assertSame(
            'SEARCH',
            data_get($payload, 'mutateOperations.1.campaignOperation.create.advertisingChannelType'),
        );
    }

    public function test_google_gateway_pauses_published_campaign(): void
    {
        Http::fake([
            'googleads.googleapis.com/*/campaigns:mutate' => Http::response(['results' => [[]]], 200),
        ]);

        $campaign = PaidAdCampaign::factory()->create();
        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $campaign->team_id,
            'platform' => AdPlatform::GoogleAds,
            'ad_account_id' => '1234567890',
        ]);
        $campaignPlatform = PaidAdCampaignPlatform::factory()->published('customers/1234567890/campaigns/456')->create([
            'paid_ad_campaign_id' => $campaign->id,
            'ad_platform_connection_id' => $connection->id,
            'platform' => AdPlatform::GoogleAds,
        ]);

        app(GoogleAdsGateway::class)->pause($campaignPlatform->load('connection'));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request)
        {
            return str_contains($request->url(), 'campaigns:mutate')
                && data_get($request->data(), 'operations.0.update.status') === 'PAUSED';
        });
    }
}
