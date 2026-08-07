<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string, 3: Enterprise}
     */
    private function adminWithToken(): array
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
            'name' => 'API Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $token = $user->createToken('idoneo-projects-test')->plainTextToken;

        return [$user, $team, $token, $client];
    }

    public function test_can_list_project_statuses(): void
    {
        [, , $token] = $this->adminWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/project-statuses');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'translated_name'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_can_create_list_show_update_and_delete_project(): void
    {
        [$user, $team, $token, $client] = $this->adminWithToken();

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects', [
                'name' => 'SPA Project',
                'status_id' => 1,
                'enterprise_id' => $client->id,
                'responsible_id' => $user->id,
                'description' => 'Created from API',
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'SPA Project')
            ->assertJsonPath('data.team_id', $team->id);

        $projectId = $create->json('data.id');
        $this->assertNotNull($projectId);
        $this->assertNotNull($create->json('data.board_id'));
        $this->assertDatabaseHas('task_boards', [
            'id' => $create->json('data.board_id'),
            'team_id' => $team->id,
        ]);

        $list = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects');

        $list->assertOk()
            ->assertJsonPath('success', true);

        $names = collect($list->json('data.data'))->pluck('name');
        $this->assertTrue($names->contains('SPA Project'));

        $show = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$projectId);

        $show->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'SPA Project')
            ->assertJsonStructure([
                'data' => [
                    'tasks',
                    'tasks_count',
                    'status',
                    'board',
                ],
            ]);

        $update = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/'.$projectId, [
                'name' => 'SPA Project Updated',
                'status_id' => 2,
            ]);

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'SPA Project Updated')
            ->assertJsonPath('data.status_id', 2);

        $delete = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/projects/'.$projectId);

        $delete->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('projects', ['id' => $projectId]);
    }

    public function test_collaborator_cannot_delete_project(): void
    {
        [, $team, , $client] = $this->adminWithToken();

        $collaborator = User::factory()->create();
        $team->users()->attach($collaborator, ['role' => 'editor']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();
        $collaborator->assignRole('collaborator');

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Owned by collaborator',
            'responsible_id' => $collaborator->id,
            'status_id' => 1,
        ]);

        $token = $collaborator->createToken('collab-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/projects/'.$project->id);

        $response->assertForbidden();
        $this->assertNull(Project::withoutGlobalScopes()->find($project->id)?->deleted_at);
    }

    public function test_unauthenticated_cannot_access_projects(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->postJson('/api/projects', [])->assertUnauthorized();
    }

    public function test_project_stats_cards_match_backend_groups(): void
    {
        [$user, $team, $token, $client] = $this->adminWithToken();

        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Budget',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);
        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Budgeted',
            'responsible_id' => $user->id,
            'status_id' => 2,
        ]);
        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'In Progress',
            'responsible_id' => $user->id,
            'status_id' => 9,
        ]);
        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'To Invoice',
            'responsible_id' => $user->id,
            'status_id' => 11,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_projects', 4)
            ->assertJsonPath('data.cards.0.key', 'budget')
            ->assertJsonPath('data.cards.0.count', 1)
            ->assertJsonPath('data.cards.0.status_ids', [1])
            ->assertJsonPath('data.cards.1.key', 'budgeted')
            ->assertJsonPath('data.cards.1.count', 1)
            ->assertJsonPath('data.cards.2.key', 'in_progress')
            ->assertJsonPath('data.cards.2.count', 1)
            ->assertJsonPath('data.cards.2.status_ids', [3, 7, 8, 9])
            ->assertJsonPath('data.cards.3.key', 'to_invoice')
            ->assertJsonPath('data.cards.3.count', 1)
            ->assertJsonPath('data.cards.3.status_ids', [10, 11]);

        $filtered = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects?status_ids=3,7,8,9');

        $filtered->assertOk();
        $names = collect($filtered->json('data.data'))->pluck('name');
        $this->assertTrue($names->contains('In Progress'));
        $this->assertFalse($names->contains('Budget'));
    }
}
