<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavbarTeamRoleDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_navbar_shows_current_team_membership_role(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');

        $otherOwner = User::factory()->withPersonalTeam()->create();
        $memberTeam = Team::factory()->create(['user_id' => $otherOwner->id]);
        $user->teams()->attach($memberTeam->id, ['role' => 'collaborator']);
        $user->switchTeam($memberTeam);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Collaborator');
    }

    public function test_owner_navbar_shows_admin_role_regardless_of_global_role(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('collaborator');
        $user->switchTeam($user->currentTeam);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee(__('Administrator'));
    }
}
