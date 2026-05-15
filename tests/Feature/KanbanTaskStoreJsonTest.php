<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\User;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KanbanTaskStoreJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_kanban_task_store_returns_json_with_task_id(): void
    {
        $this->seed(TaskStatusSeeder::class);

        $user = User::factory()->withPersonalTeam()->create();
        $teamId = $user->currentTeam->id;
        $status = TaskStatus::first();

        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'Test Board',
            'description' => null,
            'is_default' => 1,
            'order' => 0,
        ]);

        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'task.store',
            'guard_name' => 'web',
        ]));

        $now = now()->format('Y-m-d H:i:s');

        $response = $this->actingAs($user)->postJson(route('task.store'), [
            'title' => 'New kanban task',
            'description' => '',
            'responsible_id' => $user->id,
            'start_date' => $now,
            'due_date' => $now,
            'status_id' => $status->id,
            'category_id' => null,
            'board_id' => $board->id,
            'view' => 'kanban',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['id', 'due_date', 'start_date']);

        $taskId = $response->json('id');
        $this->assertNotNull($taskId);
        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'title' => 'New kanban task',
            'description' => '',
        ]);
    }
}
