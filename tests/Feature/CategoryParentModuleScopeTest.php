<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryParentModuleScopeTest extends TestCase
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

    public function test_create_category_form_only_lists_parents_from_selected_module(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $moduleA = Module::query()->create([
            'name' => 'Module A',
            'key' => 'mod-a-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);
        $moduleB = Module::query()->create([
            'name' => 'Module B',
            'key' => 'mod-b-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $moduleA->id,
            'name' => 'Parent Only In A',
            'parent_id' => null,
            'status' => 1,
        ]);
        Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $moduleB->id,
            'name' => 'Parent Only In B',
            'parent_id' => null,
            'status' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('categories.create', ['module_id' => $moduleA->id]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, preg_match('/<select[^>]*\bid="parent_id"[^>]*>(.*?)<\/select>/s', $html, $matches));
        $selectInner = $matches[1] ?? '';
        $this->assertStringContainsString('Parent Only In A', $selectInner);
        $this->assertStringNotContainsString('Parent Only In B', $selectInner);
    }
}
