<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectBoardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
            TaskStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: string, 2: Project, 3: Task}
     */
    private function projectWithTask(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Board Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Project board',
            'description' => 'Test board',
            'is_default' => false,
            'order' => 0,
        ]);

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'board_id' => $board->id,
            'name' => 'Board Project',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $todo = TaskStatus::where('name', 'TO_DO')->firstOrFail();

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'board_id' => $board->id,
            'title' => 'First task',
            'responsible_id' => $user->id,
            'status_id' => $todo->id,
            'order' => 1,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $token = $user->createToken('board-test')->plainTextToken;

        return [$user, $token, $project, $task];
    }

    public function test_board_returns_columns_and_tasks(): void
    {
        [, $token, $project, $task] = $this->projectWithTask();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/board');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonStructure([
                'data' => [
                    'project' => ['id', 'name', 'board_id'],
                    'board' => ['id', 'name'],
                    'columns' => [
                        '*' => ['id', 'name', 'translated_name', 'tasks'],
                    ],
                ],
            ]);

        $allTaskIds = collect($response->json('data.columns'))
            ->flatMap(fn ($column) => collect($column['tasks'])->pluck('id'))
            ->all();

        $this->assertContains($task->id, $allTaskIds);
    }

    public function test_can_reorder_task_on_board(): void
    {
        [, $token, $project, $task] = $this->projectWithTask();
        $inProgress = TaskStatus::where('name', 'IN_PROGRESS')->firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/'.$project->id.'/board/reorder', [
                'task_id' => $task->id,
                'status_id' => $inProgress->id,
                'order' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.status_id', $inProgress->id)
            ->assertJsonPath('data.order', 0);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status_id' => $inProgress->id,
            'order' => 0,
        ]);
    }

    public function test_can_update_and_delete_task_via_api(): void
    {
        [$user, $token, $project, $task] = $this->projectWithTask();

        $update = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/tasks/'.$task->id, [
                'title' => 'Renamed task',
                'description' => 'Updated from SPA',
                'estimated_hours' => 2.5,
                'responsible_id' => $user->id,
            ]);

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Renamed task')
            ->assertJsonPath('data.responsible.id', $user->id);

        $this->assertEquals(2.5, (float) $update->json('data.estimated_hours'));

        $board = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/board');

        $board->assertOk();
        $boardTask = collect($board->json('data.columns'))
            ->flatMap(fn ($column) => $column['tasks'])
            ->firstWhere('id', $task->id);

        $this->assertNotNull($boardTask);
        $this->assertSame($user->id, $boardTask['responsible_id']);
        $this->assertEquals(2.5, (float) $boardTask['estimated_hours']);

        $createOnBoard = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks', [
                'title' => 'Board task',
                'project_id' => $project->id,
                'responsible_id' => $user->id,
            ]);

        $createOnBoard->assertCreated()
            ->assertJsonPath('success', true);

        $newTaskId = $createOnBoard->json('data.task_id');
        $this->assertDatabaseHas('tasks', [
            'id' => $newTaskId,
            'board_id' => $project->board_id,
        ]);

        $delete = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/tasks/'.$task->id);

        $delete->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
