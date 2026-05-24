<?php

namespace Tests\Feature;

use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\User;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KanbanCategorySelectUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kanban_sidebar_includes_module_category_management_scripts(): void
    {
        $this->seed(TaskStatusSeeder::class);

        $user = User::factory()->withPersonalTeam()->create();
        $teamId = $user->currentTeam->id;

        TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'Test Board',
            'description' => null,
            'is_default' => 1,
            'order' => 0,
        ]);

        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'task.index',
            'guard_name' => 'web',
        ]));

        Permission::firstOrCreate([
            'name' => 'category.index',
            'guard_name' => 'web',
        ]);

        $response = $this->actingAs($user)->get(route('task.index', ['view' => 'kanban']));

        $response->assertOk();
        $response->assertSee('humaKanbanRebuildCategorySelect', false);
        $response->assertSee('data-module-key="tasks"', false);
        $response->assertSee('id="label"', false);
        $response->assertSee('quickStoreUrl', false);
        $response->assertSee('humaKanbanAfterModuleCategoryQuickStore', false);
        $response->assertDontSee('kanban-module-cat-mgr-label-tasks', false);
    }
}
