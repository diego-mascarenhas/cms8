<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryQuickStoreTest extends TestCase
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

    public function test_quick_store_requires_authentication(): void
    {
        $response = $this->postJson(route('categories.quick-store'), [
            'name' => 'New Category',
            'module_key' => 'products',
        ]);

        $response->assertStatus(401);
    }

    public function test_quick_store_creates_category_for_module(): void
    {
        $user = $this->actingAdmin();
        $module = Module::create([
            'name' => 'Products',
            'key' => 'products',
            'icon' => 'ti-package',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $response = $this->actingAs($user)->postJson(route('categories.quick-store'), [
            'name' => 'Handbags',
            'module_key' => 'products',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('existing', false);
        $response->assertJsonStructure(['category' => ['id', 'name']]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Handbags',
            'module_id' => $module->id,
            'team_id' => $user->currentTeam->id,
            'parent_id' => null,
            'status' => 1,
        ]);
    }

    public function test_quick_store_returns_existing_when_name_matches(): void
    {
        $user = $this->actingAdmin();
        $module = Module::create([
            'name' => 'Products',
            'key' => 'products',
            'icon' => 'ti-package',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $first = Category::factory()->create([
            'name' => 'Shoes',
            'module_id' => $module->id,
            'team_id' => $user->currentTeam->id,
            'parent_id' => null,
            'status' => 1,
        ]);

        $response = $this->actingAs($user)->postJson(route('categories.quick-store'), [
            'name' => 'SHOES',
            'module_key' => 'products',
        ]);

        $response->assertOk();
        $response->assertJsonPath('existing', true);
        $response->assertJsonPath('category.id', $first->id);

        $this->assertSame(1, Category::query()
            ->where('team_id', $user->currentTeam->id)
            ->where('module_id', $module->id)
            ->whereNull('parent_id')
            ->count());
    }
}
