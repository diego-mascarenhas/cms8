<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Support\AffiliateCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSettingsAffiliatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_can_update_affiliate_commission_on_platform_team(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->assignRole('root');
        $user->forceFill(['current_team_id' => $team->id])->save();

        config(['humano_pricing.platform_team_id' => $team->id]);

        $response = $this->actingAs($user)->put(route('team-settings.update', $team), [
            'affiliates' => [
                'affiliate_commission_percent' => 28,
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame(28.0, AffiliateCommission::percent());
    }

    public function test_admin_cannot_update_affiliate_commission(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->assignRole('admin');
        $user->forceFill(['current_team_id' => $team->id])->save();

        config([
            'humano_pricing.platform_team_id' => $team->id,
            'humano_pricing.affiliate_commission_percent' => 30,
        ]);

        $this->actingAs($user)->put(route('team-settings.update', $team), [
            'affiliates' => [
                'affiliate_commission_percent' => 50,
            ],
        ]);

        $this->assertSame(30.0, AffiliateCommission::percent());
    }

    public function test_non_root_cannot_open_affiliates_settings_page(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->assignRole('admin');
        $user->forceFill(['current_team_id' => $team->id])->save();

        config(['humano_pricing.platform_team_id' => $team->id]);

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'affiliates']))
            ->assertForbidden();
    }

    public function test_root_cannot_open_affiliates_settings_on_non_platform_team(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $otherTeam = Team::factory()->create();
        $user->assignRole('root');
        $user->forceFill(['current_team_id' => $team->id])->save();

        config(['humano_pricing.platform_team_id' => $otherTeam->id]);

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'affiliates']))
            ->assertForbidden();
    }

    public function test_presentacion_afiliados_injects_commission_percent(): void
    {
        config(['humano_pricing.affiliate_commission_percent' => 30]);

        $response = $this->get(route('presentacion.show', 'afiliados'));

        $response->assertOk();
        $response->assertSee('30%', false);
        $response->assertDontSee('__AFFILIATE_COMMISSION_PERCENT__', false);
    }
}
