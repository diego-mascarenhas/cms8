<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryRedirectAfterSaveTest extends TestCase
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

    public function test_category_update_redirects_to_index_with_module_filter_from_return_module_id(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->create([
            'name' => 'Filter Mod',
            'key' => 'filter-mod-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Section Alpha',
            'parent_id' => null,
            'status' => true,
        ]);

        $response = $this->actingAs($user)->put(route('categories.update', $category->id), [
            'id' => (string) $category->id,
            'name' => 'Section Alpha Updated',
            'module_id' => (string) $module->id,
            'return_module_id' => (string) $module->id,
            'status' => '1',
        ]);

        $response->assertRedirect(route('categories.index', ['module_id' => $module->id]));
        $this->assertSame('Section Alpha Updated', $category->fresh()->name);
    }

    public function test_category_edit_page_loads_with_module_id_query(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->create([
            'name' => 'Edit Query Mod',
            'key' => 'edit-query-mod-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Editable Row',
            'parent_id' => null,
            'status' => true,
        ]);

        $this->actingAs($user)
            ->get(route('categories.edit', ['id' => $category->id, 'module_id' => $module->id]))
            ->assertOk()
            ->assertSee('name="return_module_id"', false);
    }
}
