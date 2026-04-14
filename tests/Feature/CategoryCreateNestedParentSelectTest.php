<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryCreateNestedParentSelectTest extends TestCase
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

    public function test_create_form_includes_nested_parent_in_parent_select(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->create([
            'name' => 'Nested Parent Mod',
            'key' => 'nested-parent-mod-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $root = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Root Row',
            'parent_id' => null,
            'status' => true,
        ]);

        $child = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Child Row',
            'parent_id' => $root->id,
            'status' => true,
        ]);

        $response = $this->actingAs($user)->get(route('categories.create', [
            'parent_id' => $child->id,
            'module_id' => $module->id,
        ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/<option[^>]*value="'.preg_quote((string) $child->id, '/').'"[^>]*selected/s',
            $html,
        );
    }
}
