<?php

namespace Tests\Feature;

use App\Models\AdPlatformConnection;
use App\Models\Module;
use App\Models\PaidAdCampaign;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidAdCampaignCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_404_when_module_disabled(): void
    {
        $user = $this->createUserWithRole('admin');

        $this->actingAs($user)->get(route('paid-ads.index'))->assertNotFound();
    }

    public function test_admin_can_view_index_when_module_enabled(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enablePaidAdsModule($user);

        $this->actingAs($user)->get(route('paid-ads.index'))->assertOk();
    }

    public function test_admin_can_create_campaign(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enablePaidAdsModule($user);

        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('paid-ads.store'), [
            'name' => 'Q3 Search push',
            'objective' => 'leads',
            'budget_type' => 'daily',
            'budget_amount' => 25.50,
            'currency' => 'EUR',
            'platforms' => [$connection->id],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('paid_ad_campaigns', [
            'name' => 'Q3 Search push',
            'team_id' => $user->currentTeam->id,
            'objective' => 'leads',
        ]);

        $this->assertDatabaseHas('paid_ad_campaign_platforms', [
            'ad_platform_connection_id' => $connection->id,
            'platform' => $connection->platform->value,
        ]);
    }

    public function test_client_role_cannot_create_campaign(): void
    {
        $user = $this->createUserWithRole('client');
        $this->enablePaidAdsModule($user);

        $this->actingAs($user)->post(route('paid-ads.store'), [
            'name' => 'Nope',
            'objective' => 'traffic',
            'budget_type' => 'daily',
            'currency' => 'EUR',
        ])->assertDeniedForBrowser();
    }

    public function test_admin_can_delete_campaign(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enablePaidAdsModule($user);

        $campaign = PaidAdCampaign::factory()->create([
            'team_id' => $user->currentTeam->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('paid-ads.destroy', $campaign->id))
            ->assertOk();

        $this->assertDatabaseMissing('paid_ad_campaigns', ['id' => $campaign->id]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => $roleName]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    private function enablePaidAdsModule(User $user): void
    {
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
        $user->currentTeam->enableModule('paid_ads');
    }
}
