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
use Illuminate\Support\Facades\Auth;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskSummaryApiTest extends TestCase
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

    public function test_summary_returns_total_and_mine_pending_counts(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $other = User::factory()->create();
        $team->users()->attach($other, ['role' => 'editor']);

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Summary Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Summary board',
            'description' => 'Test board',
            'is_default' => false,
            'order' => 0,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'board_id' => $board->id,
            'name' => 'Summary Project',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $todo = TaskStatus::where('name', 'TO_DO')->firstOrFail();
        $done = TaskStatus::where('name', 'DONE')->firstOrFail();

        Task::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'board_id' => $board->id,
            'title' => 'Mine pending',
            'responsible_id' => $user->id,
            'status_id' => $todo->id,
            'order' => 1,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        Task::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'board_id' => $board->id,
            'title' => 'Other pending',
            'responsible_id' => $other->id,
            'status_id' => $todo->id,
            'order' => 2,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        Task::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'board_id' => $board->id,
            'title' => 'Mine done',
            'responsible_id' => $user->id,
            'status_id' => $done->id,
            'order' => 3,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $token = $user->createToken('summary-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tasks/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.mine', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tasks?pending_only=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Mine pending');

        $all = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tasks?pending_only=1&all=1')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->assertEqualsCanonicalizing(
            ['Mine pending', 'Other pending'],
            collect($all->json('data'))->pluck('title')->all(),
        );

        $other->forceFill(['current_team_id' => $team->id])->save();
        $otherToken = $other->createToken('summary-other')->plainTextToken;
        Auth::forgetGuards();
        $this->flushHeaders();

        $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->getJson('/api/tasks?pending_only=1&all=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Other pending');
    }

    public function test_collaborator_sees_open_tasks_on_participating_projects(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);

        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->ownedTeams()->first();
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $owner->assignRole('admin');

        $collaborator = User::factory()->create();
        $team->users()->attach($collaborator, ['role' => 'editor']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();
        $collaborator->assignRole('collaborator');

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Collab Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $mineBoard = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Mine board',
            'description' => 'Participating',
            'is_default' => false,
            'order' => 0,
        ]);
        $otherBoard = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Other board',
            'description' => 'Not participating',
            'is_default' => false,
            'order' => 1,
        ]);

        $mine = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'board_id' => $mineBoard->id,
            'name' => 'Mine',
            'responsible_id' => $owner->id,
            'status_id' => 1,
        ]);
        $mine->participants()->attach($collaborator->id);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'board_id' => $otherBoard->id,
            'name' => 'Other',
            'responsible_id' => $owner->id,
            'status_id' => 1,
        ]);

        $todo = TaskStatus::where('name', 'TO_DO')->firstOrFail();
        $progress = TaskStatus::where('name', 'IN_PROGRESS')->firstOrFail();
        $review = TaskStatus::where('name', 'REVIEW')->firstOrFail();
        $done = TaskStatus::where('name', 'DONE')->firstOrFail();

        $this->taskOn($team->id, $mineBoard->id, 'Por hacer', $owner->id, $todo->id, 1);
        $this->taskOn($team->id, $mineBoard->id, 'En progreso', $owner->id, $progress->id, 2);
        $this->taskOn($team->id, $mineBoard->id, 'En revisión', $collaborator->id, $review->id, 3);
        $this->taskOn($team->id, $mineBoard->id, 'Terminada', $owner->id, $done->id, 4);
        $this->taskOn($team->id, $otherBoard->id, 'Ajena', $owner->id, $progress->id, 5);

        $token = $collaborator->createToken('collab-tasks')->plainTextToken;

        $open = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tasks?pending_only=1&all=1')
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['Por hacer', 'En progreso', 'En revisión'],
            collect($open->json('data'))->pluck('title')->all(),
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tasks/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.mine', 1);

        Auth::forgetGuards();
        $this->flushHeaders();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tasks?pending_only=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'En revisión');
    }

    private function taskOn(int $teamId, int $boardId, string $title, int $responsibleId, int $statusId, int $order): void
    {
        Task::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'board_id' => $boardId,
            'title' => $title,
            'responsible_id' => $responsibleId,
            'status_id' => $statusId,
            'order' => $order,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);
    }
}
