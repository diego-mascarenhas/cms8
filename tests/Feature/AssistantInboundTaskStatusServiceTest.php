<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use App\Services\Assistant\AssistantInboundTaskStatusService;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantInboundTaskStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaskStatusSeeder::class);
    }

    public function test_applies_review_status_when_llm_skipped_tool(): void
    {
        $user = $this->createAdminWithTeam();
        $inProgress = TaskStatus::query()->where('name', 'IN_PROGRESS')->firstOrFail();
        $review = TaskStatus::query()->where('name', 'REVIEW')->firstOrFail();

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Probando!',
            'description' => '',
            'responsible_id' => $user->id,
            'status_id' => $inProgress->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'order' => 0,
        ]);

        $history = [
            ['direction' => 'outbound', 'body' => 'Acá está la tarea: • Probando! (id: '.$task->id.') - Estado actual: Por hacer'],
        ];

        $applied = app(AssistantInboundTaskStatusService::class)->tryApplyFromUserMessage(
            $user,
            (int) $user->currentTeam->id,
            'Ahora pasarla a revisión',
            $history,
            [],
        );

        $this->assertNotNull($applied);
        $this->assertSame('REVIEW', $applied['update']['status_name']);
        $task->refresh();
        $this->assertSame((int) $review->id, (int) $task->status_id);
    }

    public function test_applies_status_on_short_confirmation_after_proposal(): void
    {
        $user = $this->createAdminWithTeam();
        $toDo = TaskStatus::query()->where('name', 'TO_DO')->firstOrFail();
        $inProgress = TaskStatus::query()->where('name', 'IN_PROGRESS')->firstOrFail();

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Probando!',
            'description' => '',
            'responsible_id' => $user->id,
            'status_id' => $toDo->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'order' => 0,
        ]);

        $history = [
            ['direction' => 'outbound', 'body' => '¿La paso a En progreso? (id: '.$task->id.')'],
        ];

        $applied = app(AssistantInboundTaskStatusService::class)->tryApplyFromUserMessage(
            $user,
            (int) $user->currentTeam->id,
            'Si',
            $history,
            [],
        );

        $this->assertNotNull($applied);
        $task->refresh();
        $this->assertSame((int) $inProgress->id, (int) $task->status_id);
    }

    public function test_applies_done_status_from_completada_phrase(): void
    {
        $user = $this->createAdminWithTeam();
        $inProgress = TaskStatus::query()->where('name', 'IN_PROGRESS')->firstOrFail();
        $done = TaskStatus::query()->where('name', 'DONE')->firstOrFail();

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'title' => 'Probando!',
            'description' => '',
            'responsible_id' => $user->id,
            'status_id' => $inProgress->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'order' => 0,
        ]);

        $applied = app(AssistantInboundTaskStatusService::class)->tryApplyFromUserMessage(
            $user,
            (int) $user->currentTeam->id,
            'Pasarla a Completada',
            [['direction' => 'outbound', 'body' => 'Probando! (id: '.$task->id.')']],
            [],
        );

        $this->assertNotNull($applied);
        $task->refresh();
        $this->assertSame((int) $done->id, (int) $task->status_id);
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
