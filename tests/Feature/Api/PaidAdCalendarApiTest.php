<?php

namespace Tests\Feature\Api;

use App\Enums\AdConnectionStatus;
use App\Enums\AdPlatform;
use App\Enums\PaidAdObjective;
use App\Models\AdPlatformConnection;
use App\Models\Module;
use App\Models\PaidAdCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidAdCalendarApiTest extends TestCase
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
    private function adminWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        foreach (['paid_ads', 'calendar'] as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $key === 'paid_ads' ? 'Paid Ads' : 'Calendar',
                    'icon' => $key === 'paid_ads' ? 'target-arrow' : 'calendar',
                    'description' => $key,
                    'is_core' => false,
                    'status' => 1,
                ],
            );
            $team->enableModule($key);
        }

        $token = $user->createToken('idoneo-ads-calendar-test')->plainTextToken;

        return [$user, $team->fresh(), $token];
    }

    public function test_scheduled_campaign_appears_on_calendar_and_humano_event(): void
    {
        [$user, $team, $token] = $this->adminWithToken();

        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'platform' => AdPlatform::Meta,
            'status' => AdConnectionStatus::Active,
        ]);

        $start = now()->startOfMonth()->addDays(10)->setTime(9, 0);
        $end = $start->copy()->addDays(5);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads', [
                'name' => 'Pauta agosto',
                'objective' => PaidAdObjective::Traffic->value,
                'budget_type' => 'daily',
                'budget_amount' => 40,
                'currency' => 'EUR',
                'start_at' => $start->toIso8601String(),
                'end_at' => $end->toIso8601String(),
                'platforms' => [$connection->id],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Pauta agosto');

        $campaignId = (int) $create->json('data.id');
        $campaign = PaidAdCampaign::query()->find($campaignId);

        $this->assertNotNull($campaign?->calendar_event_id);
        $this->assertDatabaseHas('calendar_events', [
            'id' => $campaign->calendar_event_id,
            'team_id' => $team->id,
            'title' => 'Ads · Pauta agosto',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/calendar?from='.$start->toDateString().'&to='.$end->toDateString())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $campaignId)
            ->assertJsonPath('data.0.platforms.0.platform', AdPlatform::Meta->value)
            ->assertJsonPath('unscheduled', []);
    }

    public function test_campaign_without_dates_is_unscheduled(): void
    {
        [$user, $team, $token] = $this->adminWithToken();

        PaidAdCampaign::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'name' => 'Sin fecha',
            'start_at' => null,
            'end_at' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/calendar?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString())
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('unscheduled.0.name', 'Sin fecha');
    }

    public function test_lookups_include_platform_catalog(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/paid-ads/lookups')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'platforms' => [
                        ['key', 'label', 'color', 'connected', 'connection_id'],
                    ],
                ],
            ])
            ->assertJsonPath('data.platforms.0.connected', false);
    }
}
