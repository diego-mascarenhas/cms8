<?php

namespace Tests\Feature;

use App\Models\AdPlatformConnection;
use App\Models\Module;
use App\Models\PaidAdCampaign;
use App\Models\PaidAdCampaignPlatform;
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

    public function test_show_formats_spanish_dates_and_hides_empty_fields(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enablePaidAdsModule($user);

        $connection = AdPlatformConnection::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'ad_account_name' => 'REVISION ALPHA S.L.',
        ]);

        $campaign = PaidAdCampaign::factory()->create([
            'team_id' => $user->currentTeam->id,
            'created_by' => $user->id,
            'budget_type' => 'lifetime',
            'budget_amount' => 65,
            'currency' => 'EUR',
            'start_at' => '2026-11-17 00:00:00',
            'end_at' => null,
        ]);

        PaidAdCampaignPlatform::factory()->published('cmpn_342db85b9bfc819abff93e1dee0210a1')->create([
            'paid_ad_campaign_id' => $campaign->id,
            'ad_platform_connection_id' => $connection->id,
            'platform' => $connection->platform,
        ]);

        $response = $this->actingAs($user)->get(route('paid-ads.show', $campaign->id));

        $response->assertOk();
        $response->assertSee('17 de noviembre de 2026', false);
        $response->assertSee(__('Campaign start'), false);
        $response->assertSee(route('paid-ads.index'), false);
        $response->assertSee(__('Back to campaigns'), false);
        $response->assertSee(__('Account'), false);
        $response->assertSee(__('Status'), false);
        $response->assertSee(__('Sync'), false);
        $response->assertSee('title="cmpn_342db85b9bfc819abff93e1dee0210a1"', false);
        $response->assertDontSee(__('End date'), false);
        $response->assertDontSee('—', false);
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
