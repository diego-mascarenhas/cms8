<?php

namespace Tests\Feature;

use App\Enums\AdPlatform;
use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;
use App\Services\Ads\MetaAdsGateway;
use App\Support\AdPlatformCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidAdsTeamSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_save_paid_ads_credentials_encrypted(): void
    {
        config(['services.meta_ads.app_id' => '', 'services.meta_ads.app_secret' => '']);

        $user = $this->createUserWithRole('admin');
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->put(route('team-settings.update', $team), [
                'paid_ads' => [
                    'paid_ads_meta_app_id' => 'meta-app-id',
                    'paid_ads_meta_app_secret' => 'super-secret',
                ],
            ])
            ->assertRedirect();

        // Value is retrievable (decrypted) through the team helper.
        $this->assertSame('meta-app-id', $team->fresh()->getSetting('paid_ads_meta_app_id'));
        $this->assertSame('super-secret', $team->fresh()->getSetting('paid_ads_meta_app_secret'));

        // Secret is stored encrypted at rest, not as plaintext.
        $raw = TeamSetting::query()
            ->where('team_id', $team->id)
            ->where('key', 'paid_ads_meta_app_secret')
            ->value('value');
        $this->assertNotSame('super-secret', $raw);

        // The resolver and gateway pick up the team credentials.
        $this->assertSame('meta-app-id', AdPlatformCredentials::get(AdPlatform::Meta, 'client_id', $team->fresh()));
        $this->assertTrue(app(MetaAdsGateway::class)->forTeam($team->fresh())->isConfigured());
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
}
