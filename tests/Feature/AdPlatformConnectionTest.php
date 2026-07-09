<?php

namespace Tests\Feature;

use App\Enums\AdConnectionStatus;
use App\Enums\AdPlatform;
use App\Models\AdPlatformConnection;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdPlatformConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_redirects_to_provider_when_configured(): void
    {
        config([
            'services.meta_ads.app_id' => 'app-123',
            'services.meta_ads.app_secret' => 'secret-123',
        ]);

        $user = $this->createUserWithRole('admin');
        $this->enablePaidAdsModule($user);

        $response = $this->actingAs($user)->get(route('integrations.ad-platforms.connect', AdPlatform::Meta->value));

        $response->assertRedirect();
        $this->assertStringContainsString('facebook.com', $response->headers->get('Location'));
    }

    public function test_connect_warns_when_not_configured(): void
    {
        config([
            'services.linkedin_ads.client_id' => '',
            'services.linkedin_ads.client_secret' => '',
        ]);

        $user = $this->createUserWithRole('admin');
        $this->enablePaidAdsModule($user);

        $this->actingAs($user)
            ->get(route('integrations.ad-platforms.connect', AdPlatform::LinkedIn->value))
            ->assertRedirect(route('paid-ads.connections'));
    }

    public function test_callback_exchanges_code_and_creates_pending_connection(): void
    {
        config([
            'services.meta_ads.app_id' => 'app-123',
            'services.meta_ads.app_secret' => 'secret-123',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'access_token' => 'meta-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $user = $this->createUserWithRole('admin');
        $this->enablePaidAdsModule($user);

        $this->actingAs($user)
            ->get(route('integrations.ad-platforms.callback', AdPlatform::Meta->value).'?code=abc123')
            ->assertRedirect(route('paid-ads.connections'));

        $this->assertDatabaseHas('ad_platform_connections', [
            'team_id' => $user->currentTeam->id,
            'platform' => AdPlatform::Meta->value,
            'status' => AdConnectionStatus::PendingAccount->value,
        ]);
    }

    public function test_select_account_activates_connection(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enablePaidAdsModule($user);

        $connection = AdPlatformConnection::factory()->pendingAccount()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'platform' => AdPlatform::Meta,
        ]);

        $this->actingAs($user)->post(route('integrations.ad-platforms.select-account', $connection->id), [
            'ad_account_id' => 'act_999',
            'ad_account_name' => 'Main account',
        ])->assertRedirect(route('paid-ads.connections'));

        $this->assertDatabaseHas('ad_platform_connections', [
            'id' => $connection->id,
            'ad_account_id' => 'act_999',
            'status' => AdConnectionStatus::Active->value,
        ]);
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
