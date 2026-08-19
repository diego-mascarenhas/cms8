<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepartmentIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_index_requires_authentication(): void
    {
        $response = $this->get(route('department.index'));

        $response->assertRedirect();
    }

    public function test_department_index_allows_admin(): void
    {
        $user = $this->createUserWithRole('admin');

        $response = $this->actingAs($user)->get(route('department.index'));

        $response->assertStatus(200);
    }

    public function test_department_index_denies_non_admin(): void
    {
        $user = $this->createUserWithRole('collaborator');

        $response = $this->actingAs($user)->get(route('department.index'));

        $response->assertDeniedForBrowser();
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
