<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSocialConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_admin_can_view_social_settings_page(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get(route('team-settings.social', $team));

        $response->assertOk();
        $response->assertSee(__('Social networks'), false);
    }
}
