<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryUpdateOrderTest extends TestCase
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

    public function test_update_order_json_success_message_follows_app_locale(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->create([
            'name' => 'Order Test Mod',
            'key' => 'order-test-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Root',
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $payload = [
            'module_id' => $module->id,
            'categories' => [
                ['id' => $category->id, 'parent_id' => null, 'order' => 1],
            ],
        ];

        App::setLocale('es');
        $this->actingAs($user)->postJson(route('categories.order'), $payload)
            ->assertOk()
            ->assertJson([
                'success' => 'Orden actualizada correctamente.',
            ]);

        App::setLocale('en');
        $this->actingAs($user)->postJson(route('categories.order'), [
            'module_id' => $module->id,
            'categories' => [
                ['id' => $category->id, 'parent_id' => null, 'order' => 2],
            ],
        ])->assertOk()->assertJson([
            'success' => 'Order updated successfully.',
        ]);

        $this->assertSame(2, $category->refresh()->order);
    }

    public function test_update_order_updates_shared_categories_with_null_team_id(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->create([
            'name' => 'Shared Cat Mod',
            'key' => 'shared-cat-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $shared = Category::factory()->create([
            'team_id' => null,
            'module_id' => $module->id,
            'name' => 'Shared section',
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $this->actingAs($user)->postJson(route('categories.order'), [
            'module_id' => $module->id,
            'categories' => [
                ['id' => $shared->id, 'parent_id' => null, 'order' => 7],
            ],
        ])->assertOk();

        $this->assertSame(7, $shared->refresh()->order);
    }

    public function test_update_order_normalizes_zero_parent_id_to_null(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->create([
            'name' => 'Zero Parent Mod',
            'key' => 'zero-parent-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Root',
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $this->actingAs($user)->postJson(route('categories.order'), [
            'module_id' => $module->id,
            'categories' => [
                ['id' => $category->id, 'parent_id' => 0, 'order' => 0],
            ],
        ])->assertOk();

        $this->assertNull($category->refresh()->parent_id);
    }

    public function test_update_order_can_move_category_to_root_and_reorder(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->create([
            'name' => 'Reorder Mod',
            'key' => 'reorder-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $parent = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Parent',
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        $child = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Child',
            'parent_id' => $parent->id,
            'status' => true,
            'order' => 0,
        ]);

        $this->actingAs($user)->postJson(route('categories.order'), [
            'module_id' => $module->id,
            'categories' => [
                ['id' => $child->id, 'parent_id' => null, 'order' => 0],
                ['id' => $parent->id, 'parent_id' => null, 'order' => 1],
            ],
        ])->assertOk();

        $this->assertNull($child->refresh()->parent_id);
        $this->assertSame(0, $child->order);
        $this->assertNull($parent->refresh()->parent_id);
        $this->assertSame(1, $parent->order);
    }
}
