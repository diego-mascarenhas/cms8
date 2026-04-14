<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryIndexModuleFilterTest extends TestCase
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

    public function test_categories_index_without_module_shows_prompt_instead_of_tree(): void
    {
        $user = $this->actingAdmin();

        $response = $this->actingAs($user)->get(route('categories.index'));

        $response->assertOk();
        $response->assertSee('id="categories_filter_module_id"', false);
        $response->assertSee(__('app.Select module to list categories hint'), false);
    }

    public function test_categories_index_tree_does_not_repeat_module_badge_next_to_same_named_category(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->create([
            'name' => 'Contents',
            'key' => 'contents-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Contents',
            'parent_id' => null,
            'status' => true,
        ]);

        $response = $this->actingAs($user)->get(route('categories.index', ['module_id' => $module->id]));

        $response->assertOk();
        $response->assertSee('<span class="category-name">Contents</span>', false);
        $html = $response->getContent() ?: '';
        $this->assertStringContainsString('ti-grip-vertical', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<span class="category-name">Contents<\/span>\s*<span class="badge bg-label-info[^"]*">Contents<\/span>/',
            $html,
        );
    }

    public function test_categories_index_shows_empty_state_when_module_has_no_categories(): void
    {
        $user = $this->actingAdmin();

        $module = Module::query()->create([
            'name' => 'Empty Module',
            'key' => 'empty-mod-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('categories.index', ['module_id' => $module->id]));

        $response->assertOk();
        $response->assertSee(__('app.No categories in this module'), false);
        $response->assertDontSee('id="nestable"', false);
    }

    public function test_categories_index_with_tree_includes_nestable_vendor_script(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->create([
            'name' => 'Nestable Module',
            'key' => 'nestable-mod-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Root category',
            'parent_id' => null,
            'status' => true,
        ]);

        $response = $this->actingAs($user)->get(route('categories.index', ['module_id' => $module->id]));

        $response->assertOk();
        $html = $response->getContent() ?: '';
        $this->assertStringContainsString('assets/vendor/libs/nestable/jquery.nestable.js', $html);
        $this->assertStringNotContainsString('vendors/data-tables/js/jquery.dataTables.min.js', $html);
    }

    public function test_categories_module_options_returns_empty_groups_when_module_has_no_categories(): void
    {
        $user = $this->actingAdmin();

        $module = Module::query()->create([
            'name' => 'Opts Module',
            'key' => 'opts-mod-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $this->actingAs($user)
            ->getJson(route('categories.module-options', ['module_key' => $module->key]))
            ->assertOk()
            ->assertJson(['groups' => []]);
    }
}
