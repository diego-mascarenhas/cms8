<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\TaskStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollaboratorProjectAccessTest extends TestCase
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
            ContactStatusSeeder::class,
            ProjectStatusSeeder::class,
            TaskStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
    }

    public function test_advisor_collaborator_can_open_project_in_backend(): void
    {
        [$admin, $team, $client] = $this->adminTeamAndClient();
        $advisor = $this->collaboratorOnTeam($team);

        $project = $this->createProject($team->id, $client->id, $advisor->id, 'Advisor Project');

        $this->actingAs($advisor)
            ->get(route('project.show', $project->id))
            ->assertOk()
            ->assertSee('Advisor Project', false);

        $this->actingAs($advisor)
            ->get(route('project-list'))
            ->assertOk();
    }

    public function test_assigned_collaborator_can_open_project_in_backend(): void
    {
        [$admin, $team, $client] = $this->adminTeamAndClient();
        $assignee = $this->collaboratorOnTeam($team);

        $project = $this->createProject($team->id, $client->id, $admin->id, 'Assigned Project');
        $this->assignCollaboratorToProject($assignee, $project, $team->id, $admin->id);

        $this->actingAs($assignee)
            ->get(route('project.show', $project->id))
            ->assertOk()
            ->assertSee('Assigned Project', false);
    }

    public function test_task_responsible_collaborator_can_open_project_in_backend(): void
    {
        [$admin, $team, $client] = $this->adminTeamAndClient();
        $taskOwner = $this->collaboratorOnTeam($team);

        $project = $this->createProject($team->id, $client->id, $admin->id, 'Task Owner Project');
        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Board for '.$project->name,
            'is_default' => false,
            'order' => 1,
        ]);
        $project->forceFill(['board_id' => $board->id])->save();

        Task::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'board_id' => $board->id,
            'title' => 'Implement landing',
            'responsible_id' => $taskOwner->id,
            'estimated_hours' => 2,
            'status_id' => TaskStatus::query()->orderBy('id')->value('id'),
            'order' => 1,
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
        ]);

        $this->actingAs($taskOwner)
            ->get(route('project.show', $project->id))
            ->assertOk()
            ->assertSee('Task Owner Project', false);
    }

    public function test_unrelated_collaborator_cannot_open_or_list_other_project(): void
    {
        [$admin, $team, $client] = $this->adminTeamAndClient();
        $outsider = $this->collaboratorOnTeam($team);

        $project = $this->createProject($team->id, $client->id, $admin->id, 'Hidden Project');

        $this->actingAs($outsider)
            ->get(route('project.show', $project->id))
            ->assertNotFound();

        $this->assertFalse(
            Project::query()->whereKey($project->id)->exists(),
        );
    }

    public function test_assigned_collaborator_can_list_and_show_project_via_api(): void
    {
        [$admin, $team, $client] = $this->adminTeamAndClient();
        $assignee = $this->collaboratorOnTeam($team);
        $token = $assignee->createToken('collab-project-access')->plainTextToken;

        $project = $this->createProject($team->id, $client->id, $admin->id, 'API Assigned');
        $this->assignCollaboratorToProject($assignee, $project, $team->id, $admin->id);
        $hidden = $this->createProject($team->id, $client->id, $admin->id, 'API Hidden');

        $list = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects');

        $list->assertOk();
        $ids = collect($list->json('data.data'))->pluck('id')->all();
        $this->assertContains($project->id, $ids);
        $this->assertNotContains($hidden->id, $ids);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'API Assigned');
    }

    public function test_assigned_collaborator_sees_project_in_datatable_and_not_unrelated_ones(): void
    {
        [$admin, $team, $client] = $this->adminTeamAndClient();
        $assignee = $this->collaboratorOnTeam($team);

        $visible = $this->createProject($team->id, $client->id, $admin->id, 'Visible Assigned');
        $this->assignCollaboratorToProject($assignee, $visible, $team->id, $admin->id);
        $this->createProject($team->id, $client->id, $admin->id, 'Hidden Other');

        $response = $this->actingAs($assignee)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('project-list'));

        $response->assertOk();
        $payload = (string) json_encode($response->json('data'));
        $this->assertStringContainsString('Visible Assigned', $payload);
        $this->assertStringNotContainsString('Hidden Other', $payload);
    }

    public function test_advisor_can_create_and_edit_budget_on_project_form(): void
    {
        [$admin, $team, $client] = $this->adminTeamAndClient();
        $advisor = $this->collaboratorOnTeam($team);
        $project = $this->createProject($team->id, $client->id, $advisor->id, 'Budget Form');

        $this->actingAs($advisor)
            ->get(route('project.create'))
            ->assertOk()
            ->assertSee(__('Budget received'), false)
            ->assertSee('generate-budget-spec', false);

        $this->actingAs($advisor)
            ->get(route('project.edit', $project->id))
            ->assertOk()
            ->assertSee(__('Budget received'), false)
            ->assertSee('generate-budget-spec', false);
    }

    public function test_authorized_collaborator_can_update_project_price_and_budget(): void
    {
        [$admin, $team, $client] = $this->adminTeamAndClient();
        $assignee = $this->collaboratorOnTeam($team);
        $project = $this->createProject($team->id, $client->id, $admin->id, 'Priced Project');
        $this->assignCollaboratorToProject($assignee, $project, $team->id, $admin->id);

        $token = $assignee->createToken('collab-budget')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/'.$project->id, [
                'price' => 2500,
                'discount' => 10,
                'data' => [
                    'budget_given' => 'Sitio web corporativo',
                    'suggested_tasks' => [
                        [
                            'title' => 'Home',
                            'estimated_hours' => 8,
                            'unit_price' => 800,
                            'included' => true,
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $project->refresh();
        $this->assertEquals(2500, (float) $project->price);
        $this->assertEquals(10, (float) $project->discount);
        $this->assertSame('Sitio web corporativo', data_get($project->data, 'budget_given'));
        $this->assertSame(800, (int) data_get($project->data, 'suggested_tasks.0.unit_price'));
    }

    public function test_unrelated_collaborator_cannot_update_project_price(): void
    {
        [$admin, $team, $client] = $this->adminTeamAndClient();
        $outsider = $this->collaboratorOnTeam($team);
        $project = $this->createProject($team->id, $client->id, $admin->id, 'Locked Price');

        $token = $outsider->createToken('collab-budget-denied')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/'.$project->id, [
                'price' => 9999,
            ])
            ->assertNotFound();

        $this->assertNotEquals(9999, (float) Project::withoutGlobalScopes()->find($project->id)?->price);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: Enterprise}
     */
    private function adminTeamAndClient(): array
    {
        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->ownedTeams()->first();
        $admin->forceFill(['current_team_id' => $team->id])->save();
        $admin->assignRole('admin');

        $client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Acceso',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        return [$admin, $team, $client];
    }

    private function collaboratorOnTeam($team): User
    {
        $collaborator = User::factory()->create();
        $team->users()->attach($collaborator, ['role' => 'editor']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();
        $collaborator->assignRole('collaborator');

        return $collaborator;
    }

    private function createProject(int $teamId, int $clientId, int $responsibleId, string $name): Project
    {
        return Project::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $clientId,
            'name' => $name,
            'responsible_id' => $responsibleId,
            'status_id' => 1,
        ]);
    }

    private function assignCollaboratorToProject(User $collaborator, Project $project, int $teamId, int $creatorId): void
    {
        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'user_id' => $collaborator->id,
            'name' => $collaborator->name,
            'email' => $collaborator->email,
            'responsible_id' => $creatorId,
            'creator_id' => $creatorId,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
        ]);

        $project->collaborators()->attach($contact->id);
    }
}
