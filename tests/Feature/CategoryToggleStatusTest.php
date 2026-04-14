<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryToggleStatusTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    public function test_toggle_status_flips_category_active_flag(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->create([
            'name' => 'Toggle Mod',
            'key' => 'toggle-mod-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Toggle Me',
            'parent_id' => null,
            'status' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('categories.toggle-status', $category->id), [])
            ->assertOk()
            ->assertJsonPath('status', 0);

        $this->assertFalse($category->fresh()->status);

        $this->actingAs($user)
            ->post(route('categories.toggle-status', $category->id), [])
            ->assertOk()
            ->assertJsonPath('status', 1);

        $this->assertTrue($category->fresh()->status);
    }
}
