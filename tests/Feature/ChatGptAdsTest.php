<?php

namespace Tests\Feature;

use App\Enums\AdPlatform;
use App\Enums\AdPublishStatus;
use App\Enums\PaidAdCampaignStatus;
use App\Enums\PaidAdObjective;
use App\Models\AdPlatformConnection;
use App\Models\Module;
use App\Models\PaidAdCampaign;
use App\Models\PaidAdCampaignPlatform;
use App\Models\User;
use App\Services\Ads\ChatGptAdsGateway;
use App\Services\PaidAdPublishOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatGptAdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_connects_the_ad_account(): void
    {
        Http::fake([
            'api.ads.openai.com/v1/ad_account' => Http::response([
                'id' => 'adacct_123',
                'account_name' => 'Acme campaigns',
                'currency_code' => 'USD',
                'timezone' => 'UTC',
            ], 200),
        ]);

        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/connections/chatgpt/api-key', [
                'api_key' => 'sk-ads-test',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'chatgpt')
            ->assertJsonPath('data.ad_account_id', 'adacct_123')
            ->assertJsonPath('data.status', 'active');

        $connection = AdPlatformConnection::query()->withoutGlobalScopes()->where('platform', AdPlatform::ChatGpt)->first();
        $this->assertNotNull($connection);
        $this->assertSame('sk-ads-test', $connection->access_token);
        $this->assertSame('Acme campaigns', $connection->ad_account_name);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/connections')
            ->assertOk()
            ->assertJsonFragment([
                'platform' => 'chatgpt',
                'auth' => 'api_key',
                'configured' => true,
                'ad_account_id' => 'adacct_123',
            ]);

        Http::assertSent(function ($request): bool
        {
            return $request->url() === 'https://api.ads.openai.com/v1/ad_account'
                && $request->hasHeader('Authorization', 'Bearer sk-ads-test');
        });
    }

    public function test_rejected_api_key_does_not_create_a_connection(): void
    {
        Http::fake([
            'api.ads.openai.com/*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/connections/chatgpt/api-key', [
                'api_key' => 'sk-ads-bad',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('ad_platform_connections', 0);
    }

    public function test_publish_creates_paused_campaign_group_and_chat_card(): void
    {
        $campaignPlatform = $this->campaignPlatform();

        Http::preventStrayRequests();
        Http::fake([
            'api.ads.openai.com/v1/upload' => Http::response(['file_id' => 'file_123'], 200),
            'api.ads.openai.com/v1/campaigns' => Http::response(['id' => 'cmpn_101'], 200),
            'api.ads.openai.com/v1/ad_groups' => Http::response(['id' => 'adgrp_201'], 200),
            'api.ads.openai.com/v1/ads' => Http::response(['id' => 'ad_301', 'review_status' => 'in_review'], 200),
            'api.ads.openai.com/v1/*/activate' => Http::response(['status' => 'active'], 200),
        ]);

        $result = app(PaidAdPublishOrchestrator::class)->publish($campaignPlatform->campaign->fresh('platforms.connection'));

        $this->assertSame(1, $result['published']);
        $this->assertSame(0, $result['failed']);

        $campaignPlatform->refresh();
        $this->assertSame(AdPublishStatus::Published, $campaignPlatform->publish_status);
        $this->assertSame('cmpn_101', $campaignPlatform->external_campaign_id);
        $this->assertSame('adgrp_201', $campaignPlatform->platform_payload['chatgpt']['ad_group_id']);
        $this->assertSame('ad_301', $campaignPlatform->platform_payload['chatgpt']['ad_id']);
        $this->assertSame(PaidAdCampaignStatus::Active, $campaignPlatform->campaign->fresh()->status);

        Http::assertSent(function ($request): bool
        {
            return $request->url() === 'https://api.ads.openai.com/v1/campaigns'
                && $request['status'] === 'paused'
                && $request['bidding_type'] === 'clicks'
                && $request['budget']['lifetime_spend_limit_micros'] === 25_000_000;
        });

        Http::assertSent(function ($request): bool
        {
            return $request->url() === 'https://api.ads.openai.com/v1/ads'
                && $request['creative']['type'] === 'chat_card'
                && $request['creative']['title'] === 'Trail shoes'
                && $request['creative']['file_id'] === 'file_123'
                && $request['creative']['target_url'] === 'https://example.com/trail';
        });

        Http::assertSent(function ($request): bool
        {
            return $request->url() === 'https://api.ads.openai.com/v1/ad_groups'
                && $request['bidding_config']['billing_event_type'] === 'click'
                && $request['context_hints'] === ['trail', 'running'];
        });

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.ads.openai.com/v1/campaigns/cmpn_101/activate');
    }

    public function test_publish_retries_after_the_ad_request_fails(): void
    {
        $campaignPlatform = $this->campaignPlatform();

        Http::preventStrayRequests();
        Http::fake([
            'api.ads.openai.com/v1/upload' => Http::response(['file_id' => 'file_123'], 200),
            'api.ads.openai.com/v1/campaigns' => Http::response(['id' => 'cmpn_101'], 200),
            'api.ads.openai.com/v1/ad_groups' => Http::response(['id' => 'adgrp_201'], 200),
            'api.ads.openai.com/v1/ads' => Http::sequence()
                ->push(['error' => 'review'], 422)
                ->push(['id' => 'ad_301', 'review_status' => 'in_review'], 200),
            'api.ads.openai.com/v1/*/activate' => Http::response(['status' => 'active'], 200),
        ]);

        $orchestrator = app(PaidAdPublishOrchestrator::class);

        $failed = $orchestrator->publish($campaignPlatform->campaign->fresh('platforms.connection'));
        $this->assertSame(1, $failed['failed']);
        $this->assertSame('cmpn_101', $campaignPlatform->fresh()->platform_payload['chatgpt']['campaign_id']);

        $retried = $orchestrator->publish($campaignPlatform->campaign->fresh('platforms.connection'));

        $this->assertSame(1, $retried['published']);
        $this->assertSame('ad_301', $campaignPlatform->fresh()->platform_payload['chatgpt']['ad_id']);
        $this->assertCount(1, Http::recorded(function ($request, $response): bool
        {
            return $request->url() === 'https://api.ads.openai.com/v1/campaigns';
        }));
    }

    public function test_pause_and_metrics_use_the_advertiser_api(): void
    {
        Http::fake([
            'api.ads.openai.com/*' => Http::response([
                'id' => 'cmpn_101',
                'status' => 'paused',
                'data' => [
                    [
                        'readable_time' => '2026-04-02',
                        'impressions' => 100,
                        'clicks' => 4,
                        'spend' => 1.5,
                    ],
                ],
            ], 200),
        ]);

        $campaignPlatform = $this->campaignPlatform();
        $campaignPlatform->forceFill([
            'external_campaign_id' => 'cmpn_101',
            'platform_payload' => [
                'chatgpt' => [
                    'campaign_id' => 'cmpn_101',
                    'ad_group_id' => 'adgrp_201',
                    'ad_id' => 'ad_301',
                ],
            ],
        ])->save();

        $gateway = app(ChatGptAdsGateway::class);
        $gateway->pause($campaignPlatform->fresh('connection'));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.ads.openai.com/v1/campaigns/cmpn_101/pause');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.ads.openai.com/v1/ad_groups/adgrp_201/pause');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.ads.openai.com/v1/ads/ad_301/pause');

        $metrics = $gateway->getMetrics(
            $campaignPlatform->fresh(),
            now()->parse('2026-04-01'),
            now()->parse('2026-04-07'),
        );

        $this->assertCount(1, $metrics);
        $this->assertSame(100, $metrics[0]->impressions);
        $this->assertSame(4, $metrics[0]->clicks);
        $this->assertSame(1.5, $metrics[0]->spend);
    }

    private function campaignPlatform(): PaidAdCampaignPlatform
    {
        $campaign = PaidAdCampaign::factory()->create([
            'name' => 'Trail launch',
            'objective' => PaidAdObjective::Traffic,
            'budget_type' => 'lifetime',
            'budget_amount' => 25,
            'currency' => 'USD',
            'targeting' => ['interests' => 'trail, running'],
            'creative' => [
                'headline' => 'Trail shoes',
                'body' => 'Shoes for the next run.',
                'url' => 'https://example.com/trail',
                'assets' => [[
                    'format' => 'square',
                    'path' => 'paid-ads/1/missing.png',
                    'url' => 'https://cdn.example.com/shoe.png',
                ]],
            ],
        ]);

        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $campaign->team_id,
            'platform' => AdPlatform::ChatGpt,
            'ad_account_id' => 'adacct_123',
            'access_token' => 'sk-ads-test',
            'access_token_expires_at' => null,
        ]);

        return PaidAdCampaignPlatform::factory()->create([
            'paid_ad_campaign_id' => $campaign->id,
            'ad_platform_connection_id' => $connection->id,
            'platform' => AdPlatform::ChatGpt,
        ]);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'paid_ads'],
            [
                'name' => 'Paid Ads',
                'icon' => 'target-arrow',
                'description' => 'Paid advertising campaigns',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('paid_ads');

        return [$user, $team->fresh(), $user->createToken('idoneo-ads-test')->plainTextToken];
    }
}
