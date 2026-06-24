<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Module;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\User;
use Database\Seeders\DemoKanbanTasksSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoKanbanTasksSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_kanban_tasks_seeder_populates_non_project_boards(): void
    {
        $this->seed(TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);

        $admin = User::factory()->create(['email' => 'admin@humano.app']);
        $admin->assignRole('admin');

        $team = $admin->ownedTeams()->create([
            'name' => 'Demo',
            'personal_team' => false,
        ]);
        $admin->teams()->attach($team->id, ['role' => 'admin']);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        Module::firstOrCreate(['key' => 'tasks'], ['name' => 'Tasks', 'is_core' => false]);
        $team->enableModule('tasks');

        $tasksModuleId = Module::query()->where('key', 'tasks')->value('id');
        $adminCategory = Category::query()->create([
            'name' => 'Administración',
            'module_id' => $tasksModuleId,
            'team_id' => $team->id,
            'status' => 1,
        ]);
        foreach (['Cobranza', 'Pagos', 'Presupuestos'] as $subcategory)
        {
            Category::query()->create([
                'name' => $subcategory,
                'module_id' => $tasksModuleId,
                'team_id' => $team->id,
                'parent_id' => $adminCategory->id,
                'status' => 1,
            ]);
        }

        $this->seed(DemoKanbanTasksSeeder::class);

        $kanbanBoardIds = TaskBoard::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereIn('name', ['General', 'Comercial', 'Development'])
            ->pluck('id');

        $kanbanTasks = Task::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->whereIn('board_id', $kanbanBoardIds)
            ->count();

        $this->assertGreaterThanOrEqual(36, $kanbanTasks);

        $ownerTasks = Task::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('responsible_id', $admin->id)
            ->whereIn('board_id', $kanbanBoardIds)
            ->count();

        $this->assertGreaterThanOrEqual(5, $ownerTasks);

        $ownerTasksWithoutCategory = Task::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('responsible_id', $admin->id)
            ->whereIn('board_id', $kanbanBoardIds)
            ->whereNull('category_id')
            ->count();

        $this->assertSame(0, $ownerTasksWithoutCategory);
    }
}
