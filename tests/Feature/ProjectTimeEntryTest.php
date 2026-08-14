<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\Time;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectTimeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
            TaskStatusSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    #[Test]
    public function approved_project_can_add_suggested_task_to_board(): void
    {
        [$user, $project] = $this->createApprovedProjectWithBoard();

        $this->actingAs($user)
            ->post(route('project.add-suggested-task', $project->id), [
                'title' => 'Build section A',
                'category_name' => 'Development',
                'estimated_hours' => 2.5,
                'responsible_id' => $user->id,
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('tasks', [
            'board_id' => $project->board_id,
            'title' => 'Build section A',
            'responsible_id' => $user->id,
        ]);
    }

    #[Test]
    public function admin_can_register_time_for_collaborator_and_task(): void
    {
        [$admin, $project, $task, $collaborator] = $this->createProjectWithTaskAndCollaborator();

        $this->actingAs($admin)
            ->post(route('project.time.store', $project->id), [
                'task_id' => $task->id,
                'user_id' => $collaborator->id,
                'start_time' => now()->subHour()->format('Y-m-d H:i:s'),
                'duration_hours' => 1.5,
                'description' => 'Working on dashboard section',
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $entry = Time::withoutGlobalScopes()
            ->where('task_id', $task->id)
            ->where('user_id', $collaborator->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('Working on dashboard section', $entry->description);
        $this->assertNotNull($entry->end_time);
        $this->assertGreaterThan(0, (int) $entry->duration_seconds);

        $this->actingAs($admin)
            ->get(route('project.show', $project->id))
            ->assertOk()
            ->assertSee('Working on dashboard section', false)
            ->assertSee($collaborator->name, false)
            ->assertSee($task->title, false);
    }

    #[Test]
    public function time_entry_defaults_to_authenticated_user_when_user_id_omitted(): void
    {
        [$admin, $project, $task] = $this->createProjectWithTaskAndCollaborator();

        $this->actingAs($admin)
            ->post(route('project.time.store', $project->id), [
                'task_id' => $task->id,
                'start_time' => now()->subMinutes(30)->format('Y-m-d H:i:s'),
                'end_time' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('times', [
            'task_id' => $task->id,
            'user_id' => $admin->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function createApprovedProjectWithBoard(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'name' => 'Fanyion',
            'type_id' => 1,
            'status_id' => 1,
            'responsible_id' => $user->id,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Project board',
            'description' => null,
            'is_default' => false,
            'order' => 0,
        ]);

        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'responsible_id' => $user->id,
            'board_id' => $board->id,
            'status_id' => ProjectStatus::STATUS_APPROVED,
            'name' => 'Dashboard Innovación — 4 secciones',
            'real_name' => 'Dashboard Innovación — 4 secciones',
            'data' => [
                'budget_client_response' => [
                    'status' => 'accepted',
                    'accepted_by_name' => 'Cliente',
                    'responded_at' => now()->toIso8601String(),
                ],
                'suggested_tasks' => [
                    [
                        'title' => 'Build section A',
                        'estimated_hours' => 2.5,
                        'included' => true,
                    ],
                ],
            ],
        ]));

        return [$user, $project];
    }

    /**
     * @return array{0: User, 1: Project, 2: Task, 3: User}
     */
    private function createProjectWithTaskAndCollaborator(): array
    {
        [$admin, $project] = $this->createApprovedProjectWithBoard();
        $team = $admin->currentTeam;

        $collaborator = User::factory()->create();
        $team->users()->attach($collaborator, ['role' => 'editor']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();

        $statusId = TaskStatus::query()->orderBy('id')->value('id');
        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'board_id' => $project->board_id,
            'title' => 'Section A implementation',
            'responsible_id' => $collaborator->id,
            'estimated_hours' => 2,
            'status_id' => $statusId,
            'order' => 1,
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
        ]);

        return [$admin, $project, $task, $collaborator];
    }
}
