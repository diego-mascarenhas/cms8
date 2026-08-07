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
    }
}
