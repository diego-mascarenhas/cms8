<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\User;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KanbanResponsibleFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_kanban_defaults_to_current_user_tasks(): void
    {
        [$user, $otherUser, $board, $status] = $this->kanbanSetup();

        $this->createTask($board, $status, $user, 'My task');
        $this->createTask($board, $status, $otherUser, 'Other task');

        $response = $this->actingAs($user)->get(route('task.index', ['view' => 'kanban']));

        $response->assertOk();
        $response->assertSee('My task', false);
        $response->assertDontSee('Other task', false);
        $response->assertSee('id="responsible-filter"', false);
    }

    public function test_kanban_can_filter_by_specific_responsible(): void
    {
        [$user, $otherUser, $board, $status] = $this->kanbanSetup();

        $this->createTask($board, $status, $user, 'My task');
        $this->createTask($board, $status, $otherUser, 'Other task');

        $response = $this->actingAs($user)->get(route('task.index', [
            'view' => 'kanban',
            'responsible_id' => $otherUser->id,
        ]));

        $response->assertOk();
        $response->assertDontSee('My task', false);
        $response->assertSee('Other task', false);
    }

    public function test_kanban_can_show_all_responsibles(): void
    {
        [$user, $otherUser, $board, $status] = $this->kanbanSetup();

        $this->createTask($board, $status, $user, 'My task');
        $this->createTask($board, $status, $otherUser, 'Other task');

        $response = $this->actingAs($user)->get(route('task.index', [
            'view' => 'kanban',
            'responsible_id' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('My task', false);
        $response->assertSee('Other task', false);
    }

    /**
     * @return array{0: User, 1: User, 2: TaskBoard, 3: TaskStatus}
     */
    private function kanbanSetup(): array
    {
        $this->seed(TaskStatusSeeder::class);

        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('collaborator');

        $otherUser = User::factory()->create();
        $otherUser->assignRole('collaborator');
        $user->currentTeam->users()->attach($otherUser, ['role' => 'collaborator']);

        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Test Board',
            'description' => null,
            'is_default' => 1,
            'order' => 0,
        ]);

        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'task.index',
            'guard_name' => 'web',
        ]));

        return [$user, $otherUser, $board, TaskStatus::first()];
    }

    private function createTask(TaskBoard $board, TaskStatus $status, User $responsible, string $title): Task
    {
        return Task::withoutGlobalScopes()->create([
            'team_id' => $board->team_id,
            'board_id' => $board->id,
            'responsible_id' => $responsible->id,
            'status_id' => $status->id,
            'title' => $title,
            'description' => '',
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'order' => 0,
        ]);
    }
}
