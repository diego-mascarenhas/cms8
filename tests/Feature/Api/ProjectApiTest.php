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
            ->assertJsonPath('data.status_id', 2)
            ->assertJsonPath('data.status.id', 2)
            ->assertJsonPath('data.status.name', 'BUDGETED');

        $this->assertNotEmpty($update->json('data.status.translated_name'));

        $showAfterUpdate = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$projectId);

        $showAfterUpdate->assertOk()
            ->assertJsonPath('data.status_id', 2)
            ->assertJsonPath('data.status.id', 2);

        $this->assertNotEmpty($showAfterUpdate->json('data.status.translated_name'));

        $delete = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/projects/'.$projectId);

        $delete->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('projects', ['id' => $projectId]);
    }

    public function test_can_create_project_with_budget_data(): void
    {
        [$user, , $token, $client] = $this->adminWithToken();

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects', [
                'name' => 'Plugins WordPress',
                'enterprise_id' => $client->id,
                'description' => 'WooCommerce plugin cleanup',
                'price' => 1006,
                'discount' => 0,
                'data' => [
                    'dimension' => 'Cleanup, patches and major updates with staging.',
                    'estimated_times' => '6 hours senior.',
                    'resources' => 'Senior 120 €/h',
                    'ai_usage_percent' => 0,
                    'token_consumption' => [
                        'input_tokens' => 16000000,
                        'output_tokens' => 2000000,
                        'total_tokens' => 18000000,
                        'cost_euros' => 286,
                        'billable_euros' => 286,
                        'savings_percent' => 0,
                        'currency' => 'EUR',
                    ],
                    'suggested_tasks' => [
                        [
                            'title' => 'Backup + inventario operativo',
                            'description' => 'Full backup and risk plan.',
                            'estimated_hours' => 0.5,
                            'resource_level' => 'Senior',
                            'unit_price' => 60,
                            'estimated_tokens' => 900000,
                            'included' => true,
                        ],
                        [
                            'title' => 'Parches seguros',
                            'estimated_hours' => 1,
                            'resource_level' => 'Senior',
                            'unit_price' => 120,
                            'estimated_tokens' => 2700000,
                            'included' => true,
                        ],
                    ],
                ],
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Plugins WordPress')
            ->assertJsonPath('data.status_id', 1)
            ->assertJsonPath('data.responsible_id', $user->id)
            ->assertJsonPath('data.data.suggested_tasks.0.title', 'Backup + inventario operativo')
            ->assertJsonPath('data.data.suggested_tasks.0.estimated_hours', 0.5)
            ->assertJsonPath('data.data.token_consumption.total_tokens', 18000000);

        $this->assertGreaterThan(0, (int) $create->json('totals.grand_total'));

        $previewToken = $create->json('data.data.budget_preview_token');
        $this->assertIsString($previewToken);
        $this->assertNotSame('', $previewToken);
        $this->assertSame(url('/p/budget/'.$previewToken), $create->json('preview_url'));

        $this->get(route('project.budget-preview', $previewToken))
            ->assertOk()
            ->assertSee('Backup + inventario operativo', false);
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

    public function test_collaborator_cannot_change_project_status(): void
    {
        [, $team, , $client] = $this->adminWithToken();

        $collaborator = User::factory()->create();
        $team->users()->attach($collaborator, ['role' => 'editor']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();
        $collaborator->assignRole('collaborator');

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Status locked project',
            'responsible_id' => $collaborator->id,
            'status_id' => 1,
        ]);

        $token = $collaborator->createToken('collab-status-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/'.$project->id, [
                'status_id' => 2,
            ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertSame(1, Project::withoutGlobalScopes()->find($project->id)?->status_id);
    }

    public function test_unauthenticated_cannot_access_projects(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->postJson('/api/projects', [])->assertUnauthorized();
    }

    public function test_projects_list_defaults_to_newest_first(): void
    {
        [$user, $team, $token, $client] = $this->adminWithToken();

        $older = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'AAA Older Project',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $newer = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'ZZZ Newer Project',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects');

        $response->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertSame($newer->id, $ids[0]);
        $this->assertTrue(array_search($newer->id, $ids, true) < array_search($older->id, $ids, true));
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
        // Outside the summary panel — must not dilute card percentages.
        Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Invoiced',
            'responsible_id' => $user->id,
            'status_id' => 12,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_projects', 5)
            ->assertJsonPath('data.panel_total', 4)
            ->assertJsonPath('data.cards.0.key', 'budget')
            ->assertJsonPath('data.cards.0.count', 1)
            ->assertJsonPath('data.cards.0.percentage', 25)
            ->assertJsonPath('data.cards.0.status_ids', [1])
            ->assertJsonPath('data.cards.1.key', 'budgeted')
            ->assertJsonPath('data.cards.1.count', 1)
            ->assertJsonPath('data.cards.1.percentage', 25)
            ->assertJsonPath('data.cards.2.key', 'in_progress')
            ->assertJsonPath('data.cards.2.count', 1)
            ->assertJsonPath('data.cards.2.percentage', 25)
            ->assertJsonPath('data.cards.2.status_ids', [3, 7, 8, 9])
            ->assertJsonPath('data.cards.3.key', 'to_invoice')
            ->assertJsonPath('data.cards.3.count', 1)
            ->assertJsonPath('data.cards.3.percentage', 25)
            ->assertJsonPath('data.cards.3.status_ids', [10, 11]);

        $filtered = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects?status_ids=3,7,8,9');

        $filtered->assertOk();
        $names = collect($filtered->json('data.data'))->pluck('name');
        $this->assertTrue($names->contains('In Progress'));
        $this->assertFalse($names->contains('Budget'));
    }
}
