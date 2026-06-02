<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantTaskToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaskStatusSeeder::class);
    }

    public function test_search_tasks_returns_matching_task_with_id(): void
    {
        $user = $this->createAdminWithTeam();
        $toDo = TaskStatus::query()->where('name', 'TO_DO')->firstOrFail();

        Task::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Deploy staging',
            'description' => '',
            'responsible_id' => $user->id,
            'status_id' => $toDo->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'order' => 0,
        ]);

        $out = $this->assistantTools($user)->execute('search_tasks', ['query' => 'Deploy']);

        $this->assertStringContainsString('Found 1 task', $out);
        $this->assertStringContainsString('Deploy staging', $out);
        $this->assertStringContainsString('TO_DO', $out);
    }

    public function test_list_task_statuses_includes_kanban_columns(): void
    {
        $user = $this->createAdminWithTeam();

        $out = $this->assistantTools($user)->execute('list_task_statuses', []);

        $this->assertStringContainsString('TO_DO', $out);
        $this->assertStringContainsString('IN_PROGRESS', $out);
        $this->assertStringContainsString('REVIEW', $out);
        $this->assertStringContainsString('DONE', $out);
    }

    public function test_update_task_status_moves_task_to_done_with_spanish_alias(): void
    {
        $user = $this->createAdminWithTeam();
        $toDo = TaskStatus::query()->where('name', 'TO_DO')->firstOrFail();
        $done = TaskStatus::query()->where('name', 'DONE')->firstOrFail();

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Invoice follow-up',
            'description' => '',
            'responsible_id' => $user->id,
            'status_id' => $toDo->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'order' => 0,
        ]);

        $out = $this->assistantTools($user)->execute('update_task_status', [
            'task_id' => $task->id,
            'status' => 'finalizada',
        ]);

        $this->assertStringContainsString('humano_task_status_updated:', $out);
        $this->assertStringContainsString('moved from', $out);
        $this->assertStringContainsString('DONE', $out);

        $task->refresh();
        $this->assertSame((int) $done->id, (int) $task->status_id);
    }

    public function test_update_task_status_moves_task_to_review(): void
    {
        $user = $this->createAdminWithTeam();
        $inProgress = TaskStatus::query()->where('name', 'IN_PROGRESS')->firstOrFail();
        $review = TaskStatus::query()->where('name', 'REVIEW')->firstOrFail();

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'QA checklist',
            'description' => '',
            'responsible_id' => $user->id,
            'status_id' => $inProgress->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(1)->toDateString(),
            'order' => 0,
        ]);

        $out = $this->assistantTools($user)->execute('update_task_status', [
            'task_id' => $task->id,
            'status' => 'en revisión',
        ]);

        $this->assertStringContainsString('REVIEW', $out);
        $task->refresh();
        $this->assertSame((int) $review->id, (int) $task->status_id);
    }

    public function test_create_task_uses_board_for_request_team_without_http_auth(): void
    {
        $user = $this->createAdminWithTeam();
        $teamAId = $user->currentTeam->id;

        $otherTeam = Team::factory()->create();
        TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $otherTeam->id,
            'name' => 'Other team default',
            'description' => null,
            'is_default' => true,
            'order' => 0,
        ]);

        $out = $this->assistantTools($user)->execute('create_task', [
            'title' => 'Enviar acceso al área de cliente',
            'due_days' => 1,
        ]);

        $this->assertStringContainsString('Task created:', $out);

        $task = Task::withoutGlobalScopes()
            ->where('team_id', $teamAId)
            ->where('title', 'Enviar acceso al área de cliente')
            ->first();

        $this->assertNotNull($task);
        $board = TaskBoard::withoutGlobalScopes()->find($task->board_id);
        $this->assertNotNull($board);
        $this->assertSame($teamAId, $board->team_id);
    }

    public function test_create_task_avoids_project_only_default_board(): void
    {
        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);

        $user = $this->createAdminWithTeam();
        $teamId = $user->currentTeam->id;

        $projectBoard = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'Project board',
            'description' => null,
            'is_default' => true,
            'order' => 0,
        ]);

        $generalBoard = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'General board',
            'description' => null,
            'is_default' => false,
            'order' => 1,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Test Enterprise',
            'code' => 'TST',
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'board_id' => $projectBoard->id,
            'enterprise_id' => $enterprise->id,
            'name' => 'Revision Alpha',
            'description' => null,
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $this->assistantTools($user)->execute('create_task', [
            'title' => 'Task on general kanban',
        ]);

        $task = Task::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('title', 'Task on general kanban')
            ->first();

        $this->assertNotNull($task);
        $this->assertSame((int) $generalBoard->id, (int) $task->board_id);
    }

    public function test_update_task_status_reports_already_in_target_status(): void
    {
        $user = $this->createAdminWithTeam();
        $done = TaskStatus::query()->where('name', 'DONE')->firstOrFail();

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Closed item',
            'description' => '',
            'responsible_id' => $user->id,
            'status_id' => $done->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'order' => 0,
        ]);

        $out = $this->assistantTools($user)->execute('update_task_status', [
            'task_id' => $task->id,
            'status' => 'DONE',
        ]);

        $this->assertStringContainsString('already in status', $out);
    }

    private function assistantTools(User $user): AssistantToolsService
    {
        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $user->currentTeam->id, null);

        return $service;
    }

    private function createAdminWithTeam(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);

        return $user->refresh();
    }
}
