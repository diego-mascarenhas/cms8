<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavbarTeamSwitcherVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_collaborator_with_multiple_teams_sees_team_switcher(): void
    {
        $user = $this->makeUserWithRole('collaborator');
        $this->attachSecondTeam($user);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee(__('app.profile.team.switch'));
    }

    public function test_collaborator_with_single_team_does_not_see_team_switcher(): void
    {
        $user = $this->makeUserWithRole('collaborator');

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee(__('app.profile.team.switch'));
    }

    public function test_client_with_multiple_teams_does_not_see_team_switcher(): void
    {
        $user = $this->makeUserWithRole('client');
        $this->attachSecondTeam($user);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee(__('app.profile.team.switch'));
    }

    private function makeUserWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole($role);
        $user->switchTeam($user->currentTeam);

        return $user;
    }

    private function attachSecondTeam(User $user): void
    {
        $otherOwner = User::factory()->withPersonalTeam()->create();
        $secondTeam = Team::factory()->create(['user_id' => $otherOwner->id]);
        $user->teams()->attach($secondTeam->id, ['role' => 'collaborator']);
    }
}
