<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TasksByProjectKeyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            TaskStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);
    }

    public function test_tasks_by_project_key_returns_404_for_invalid_key(): void
    {
        $response = $this->getJson('/api/tasks-by-project-key?project_key='.str_repeat('a', 64));

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => __('Proyecto no encontrado con la clave indicada.'),
            ]);
    }

    public function test_tasks_by_project_key_returns_422_when_project_key_missing(): void
    {
        $response = $this->getJson('/api/tasks-by-project-key');

        $response->assertStatus(422);
    }

    public function test_tasks_by_project_key_returns_tasks_for_valid_project(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = $user->currentTeam->id ?? $user->teams()->first()->id;
        $status = TaskStatus::first();

        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'Test Board',
            'description' => null,
            'is_default' => 1,
            'order' => 0,
        ]);

        $enterpriseId = DB::table('enterprises')->insertGetId([
            'team_id' => $teamId,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Test Enterprise',
            'code' => 'TST',
        ]);

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'board_id' => $board->id,
            'enterprise_id' => $enterpriseId,
            'name' => 'Test Project',
            'description' => null,
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        Task::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'board_id' => $board->id,
            'title' => 'First Task',
            'description' => null,
            'status_id' => $status->id,
            'responsible_id' => $user->id,
            'start_date' => now(),
            'due_date' => now()->addDays(7),
        ]);

        $projectKey = Project::keyFromId((int) $project->id);

        $response = $this->getJson('/api/tasks-by-project-key?project_key='.$projectKey);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total' => 1,
                'project' => [
                    'id' => $project->id,
                    'name' => 'Test Project',
                ],
            ])
            ->assertJsonPath('data.0.title', 'First Task');
    }

    public function test_tasks_by_project_key_returns_empty_data_when_project_has_no_board(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = $user->currentTeam->id ?? $user->teams()->first()->id;
        $enterpriseId = DB::table('enterprises')->insertGetId([
            'team_id' => $teamId,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Test Enterprise',
            'code' => 'TST',
        ]);
        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'board_id' => null,
            'enterprise_id' => $enterpriseId,
            'name' => 'Project Without Board',
            'description' => null,
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $projectKey = Project::keyFromId((int) $project->id);

        $response = $this->getJson('/api/tasks-by-project-key?project_key='.$projectKey);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [],
                'total' => 0,
                'project' => [
                    'id' => $project->id,
                    'name' => 'Project Without Board',
                ],
            ]);
    }
}
