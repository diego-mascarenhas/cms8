<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Time;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskTimerApiTest extends TestCase
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
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_task_list_includes_responsible_and_elapsed_time(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        [$admin, $team, $token, $task] = $this->taskForAdmin();

        Time::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'task_id' => $task->id,
            'start_time' => now()->subMinutes(40),
            'end_time' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tasks?pending_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.responsible.name', $admin->name)
            ->assertJsonPath('data.0.time_seconds', 2400)
            ->assertJsonPath('data.0.workers.0.name', $admin->name)
            ->assertJsonPath('data.0.workers.0.working', false);
    }

    public function test_admin_starts_timer_for_collaborator_and_it_stops_at_the_estimate(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        [$admin, $team, $token, $task] = $this->taskForAdmin();
        $task->update(['estimated_hours' => 1]);

        $collaborator = User::factory()->create(['name' => 'Ana Colaboradora']);
        $collaborator->assignRole('collaborator');
        $team->users()->attach($collaborator, ['role' => 'collaborator']);

        Time::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'task_id' => $task->id,
            'start_time' => now()->subMinutes(40),
            'end_time' => now(),
        ]);

        $started = now()->copy();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks/'.$task->id.'/start', [
                'user_id' => $collaborator->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $collaborator->id)
            ->assertJsonPath('data.user_name', 'Ana Colaboradora');

        $stopsAt = Carbon::parse($response->json('data.stops_at'));
        $this->assertEqualsWithDelta($started->copy()->addMinutes(20)->getTimestamp(), $stopsAt->getTimestamp(), 2);

        Carbon::setTestNow($started->copy()->addMinutes(25));
        $this->artisan('tasks:cap-timers')->assertSuccessful();

        $entry = Time::withoutGlobalScope('team')
            ->where('task_id', $task->id)
            ->where('user_id', $collaborator->id)
            ->first();

        $this->assertNotNull($entry?->end_time);
        $this->assertEqualsWithDelta(1200, (int) $entry->duration_seconds, 2);
    }

    public function test_collaborator_cannot_start_timer_for_someone_else(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        [$admin, $team, , $task] = $this->taskForAdmin();

        $collaborator = User::factory()->create();
        $collaborator->assignRole('collaborator');
        $team->users()->attach($collaborator, ['role' => 'collaborator']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();
        $task->update(['responsible_id' => $collaborator->id]);
        $token = $collaborator->createToken('collaborator-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks/'.$task->id.'/start', [
                'user_id' => $admin->id,
            ])
            ->assertForbidden();
    }

    public function test_project_list_marks_a_project_with_a_running_task(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        [, , $token, $task] = $this->taskForAdmin();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tasks/'.$task->id.'/start')
            ->assertCreated();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects')
            ->assertOk();

        $project = collect($response->json('data.data'))->firstWhere('name', 'Timer Project');

        $this->assertNotNull($project);
        $this->assertTrue($project['has_active_task']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string, 3: Task}
     */
    private function taskForAdmin(): array
    {
        $admin = User::factory()->withPersonalTeam()->create(['name' => 'Diego Admin']);
        $team = $admin->ownedTeams()->first();
        $admin->forceFill(['current_team_id' => $team->id])->save();
        $admin->assignRole('admin');

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Timer Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Timer board',
            'is_default' => false,
            'order' => 0,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'board_id' => $board->id,
            'name' => 'Timer Project',
            'responsible_id' => $admin->id,
            'status_id' => 1,
        ]);

        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'board_id' => $board->id,
            'title' => 'Creación de equipos',
            'responsible_id' => $admin->id,
            'status_id' => TaskStatus::where('name', 'TO_DO')->firstOrFail()->id,
            'order' => 1,
            'estimated_hours' => 4,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
        ]);

        return [$admin, $team, $admin->createToken('timer-test')->plainTextToken, $task];
    }
}
