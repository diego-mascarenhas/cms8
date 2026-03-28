<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryModuleOptionsTest extends TestCase
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

    public function test_module_options_requires_authentication(): void
    {
        $response = $this->getJson(route('categories.module-options', ['module_key' => 'products']));

        $response->assertStatus(401);
    }

    public function test_module_options_returns_groups_json(): void
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

        $parent = Category::factory()->create([
            'name' => 'Parent',
            'module_id' => $module->id,
            'team_id' => $user->currentTeam->id,
            'parent_id' => null,
            'status' => 1,
        ]);

        Category::factory()->create([
            'name' => 'Child',
            'module_id' => $module->id,
            'team_id' => $user->currentTeam->id,
            'parent_id' => $parent->id,
            'status' => 1,
        ]);

        $response = $this->actingAs($user)->getJson(route('categories.module-options', ['module_key' => 'products']));

        $response->assertOk();
        $response->assertJsonStructure(['groups']);
        $groups = $response->json('groups');
        $this->assertNotEmpty($groups);
        $this->assertSame('group', $groups[0]['type']);
        $this->assertSame('Parent', $groups[0]['label']);
        $this->assertCount(1, $groups[0]['options']);
    }
}
