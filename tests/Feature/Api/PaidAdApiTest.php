<?php

namespace Tests\Feature\Api;

use App\Enums\AdConnectionStatus;
use App\Enums\AdPlatform;
use App\Enums\PaidAdCampaignStatus;
use App\Enums\PaidAdObjective;
use App\Jobs\PublishPaidAdCampaignJob;
use App\Models\AdPlatformConnection;
use App\Models\Module;
use App\Models\PaidAdAudience;
use App\Models\PaidAdCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidAdApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithToken(bool $enableModule = true): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

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

        if ($enableModule)
        {
            $team->enableModule('paid_ads');
        }

        $token = $user->createToken('idoneo-ads-test')->plainTextToken;

        return [$user, $team->fresh(), $token];
    }

    public function test_module_missing_returns_forbidden(): void
    {
        [, , $token] = $this->adminWithToken(false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_can_crud_campaign_and_publish(): void
    {
        Queue::fake();

        [$user, $team, $token] = $this->adminWithToken();

        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'platform' => AdPlatform::Meta,
            'status' => AdConnectionStatus::Active,
        ]);

        $audience = PaidAdAudience::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'name' => 'Lookalike ES',
        ]);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads', [
                'name' => 'Spring Ads',
                'objective' => PaidAdObjective::Traffic->value,
                'budget_type' => 'daily',
                'budget_amount' => 25.5,
                'currency' => 'EUR',
                'creative' => [
                    'headline' => 'Try Idoneo',
                    'body' => 'Launch faster',
                    'url' => 'https://idoneo.test',
                ],
                'targeting' => [
                    'locations' => 'Spain',
                    'age_min' => 25,
                    'age_max' => 55,
                ],
                'platforms' => [$connection->id],
                'audiences' => [$audience->id],
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Spring Ads')
            ->assertJsonPath('data.status.key', PaidAdCampaignStatus::Draft->value)
            ->assertJsonPath('data.platforms.0.platform', AdPlatform::Meta->value);

        $id = (int) $create->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/'.$id)
            ->assertOk()
            ->assertJsonPath('data.audience_ids.0', $audience->id)
            ->assertJsonPath('data.creative.headline', 'Try Idoneo');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/paid-ads/'.$id, [
                'name' => 'Spring Ads Updated',
                'objective' => PaidAdObjective::Leads->value,
                'budget_type' => 'lifetime',
                'budget_amount' => 100,
                'currency' => 'EUR',
                'platforms' => [$connection->id],
                'audiences' => [$audience->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Spring Ads Updated')
            ->assertJsonPath('data.objective.key', PaidAdObjective::Leads->value);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/'.$id.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status.key', PaidAdCampaignStatus::Publishing->value);

        Queue::assertPushed(PublishPaidAdCampaignJob::class);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/'.$id.'/pause')
            ->assertOk()
            ->assertJsonPath('data.status.key', PaidAdCampaignStatus::Paused->value);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/paid-ads/'.$id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('paid_ad_campaigns', ['id' => $id]);
    }

    public function test_can_manage_audiences_and_dashboard(): void
    {
        [$user, $team, $token] = $this->adminWithToken();

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/audiences', [
                'name' => 'Retargeting cart',
                'type' => 'retargeting',
                'targeting_rules' => [
                    'locations' => 'Spain',
                    'interests' => 'ecommerce',
                ],
                'estimated_size' => 1200,
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Retargeting cart')
            ->assertJsonPath('data.type', 'retargeting');

        $audienceId = (int) $create->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/audiences')
            ->assertOk()
            ->assertJsonPath('data.0.id', $audienceId);

        PaidAdCampaign::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'status' => PaidAdCampaignStatus::Active,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active_campaigns', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/lookups')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['objectives', 'connections', 'audiences', 'currencies']])
            ->assertJsonPath('data.currencies', ['EUR', 'USD', 'ARS']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/paid-ads/audiences/'.$audienceId)
            ->assertOk();

        $this->assertDatabaseMissing('paid_ad_audiences', ['id' => $audienceId]);
    }

    public function test_connections_index_and_disconnect(): void
    {
        [$user, $team, $token] = $this->adminWithToken();

        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'platform' => AdPlatform::LinkedIn,
            'status' => AdConnectionStatus::Active,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/connections')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['platform' => AdPlatform::LinkedIn->value]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/paid-ads/connections/'.$connection->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('ad_platform_connections', ['id' => $connection->id]);
    }
}
