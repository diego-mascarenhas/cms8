<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Services\ProjectBudgetSpecService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
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
            CountrySeeder::class,
            LanguageSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
            ContactStatusSeeder::class,
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
        $this->postJson('/api/projects/from-brief', [])->assertUnauthorized();
    }

    public function test_can_create_project_from_brief_and_existing_client(): void
    {
        [$user, $team, $token, $client] = $this->adminWithToken();

        $this->partialMock(ProjectBudgetSpecService::class, function ($mock)
        {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn($this->fakeBudgetSpec());
        });

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/from-brief', [
                'enterprise_id' => $client->id,
                'name' => 'Landing ACME',
                'brief' => 'Landing corporativa con blog y formulario de contacto.',
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Landing ACME')
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.enterprise_id', $client->id)
            ->assertJsonPath('data.responsible_id', $user->id)
            ->assertJsonPath('data.status_id', 1)
            ->assertJsonPath('data.data.budget_given', 'Landing corporativa con blog y formulario de contacto.')
            ->assertJsonPath('data.data.suggested_tasks.0.title', 'Diseño')
            ->assertJsonPath('data.data.ai_interpretation', 'Landing corporativa');

        $this->assertNotNull($create->json('data.board_id'));
        $this->assertGreaterThan(0, (int) $create->json('totals.grand_total'));
        $this->assertSame((int) $create->json('totals.grand_total'), (int) $create->json('data.price'));
        $this->assertNotEmpty($create->json('data.data.budget_preview_token'));
    }

    public function test_can_create_project_from_brief_and_new_client(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $this->partialMock(ProjectBudgetSpecService::class, function ($mock)
        {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn($this->fakeBudgetSpec());
        });

        $module = Module::firstOrCreate(
            ['key' => 'contacts'],
            ['name' => 'Contacts', 'description' => 'Contacts', 'is_core' => 1, 'status' => 1, 'order' => 0],
        );
        $category = Category::query()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Lead web',
            'status' => 1,
            'order' => 0,
        ]);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/from-brief', [
                'contact_name' => 'Ana',
                'surname' => 'García',
                'email' => 'ana@estudio-norte.test',
                'phone' => '+34 600 000 000',
                'business_name' => 'Estudio Norte',
                'country' => 'ES',
                'category_ids' => [$category->id],
                'brief' => 'Tienda online con catálogo y pagos.',
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Estudio Norte');

        $enterprise = Enterprise::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('name', 'Estudio Norte')
            ->first();

        $this->assertNotNull($enterprise);
        $this->assertSame($enterprise->id, $create->json('data.enterprise_id'));
        $this->assertSame('ana@estudio-norte.test', $enterprise->email);
        $this->assertNotEmpty($enterprise->phone);
        $this->assertSame('ES', $enterprise->country);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'ana@estudio-norte.test')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('Ana', $contact->name);
        $this->assertSame('García', $contact->surname);
        $this->assertEquals(
            Country::query()->whereRaw('LOWER(code) = ?', ['es'])->value('id'),
            $contact->getAttributes()['country'] ?? null,
        );
        $this->assertTrue($contact->categories()->where('categories.id', $category->id)->exists());
        $this->assertTrue($contact->enterprises()->where('enterprises.id', $enterprise->id)->exists());
    }

    public function test_from_brief_rejects_client_from_another_team(): void
    {
        [, , $token] = $this->adminWithToken();
        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherTeam = $otherUser->ownedTeams()->first();
        $foreign = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $otherTeam->id,
            'name' => 'Foreign Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $this->partialMock(ProjectBudgetSpecService::class, function ($mock)
        {
            $mock->shouldReceive('generate')->never();
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/from-brief', [
                'enterprise_id' => $foreign->id,
                'brief' => 'Landing corporativa con blog y formulario de contacto.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_can_rename_quote_title_for_public_preview(): void
    {
        [$user, $team, $token, $client] = $this->adminWithToken();

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Estudio Norte',
            'real_name' => 'Estudio Norte',
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_BUDGETED,
            'data' => [
                'budget_preview_token' => 'renameToken'.bin2hex(random_bytes(8)),
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/'.$project->id, [
                'name' => 'Sitio WordPress institucional',
                'real_name' => 'Sitio WordPress institucional',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Sitio WordPress institucional')
            ->assertJsonPath('data.real_name', 'Sitio WordPress institucional');

        $this->getJson('/api/projects/funnel/budget/'.data_get($project->fresh()->data, 'budget_preview_token'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Sitio WordPress institucional')
            ->assertJsonPath('data.client_name', 'API Client');
    }

    public function test_regenerate_budget_keeps_original_brief_and_appends_note(): void
    {
        [$user, $team, $token, $client] = $this->adminWithToken();

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Landing ACME',
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_BUDGETED,
            'data' => [
                'budget_given' => 'Landing corporativa con blog y formulario.',
                'budget_preview_token' => 'regenToken'.bin2hex(random_bytes(8)),
                'suggested_tasks' => [
                    [
                        'title' => 'Diseño',
                        'estimated_hours' => 8,
                        'resource_level' => 'Senior',
                        'unit_price' => 800,
                        'included' => true,
                    ],
                ],
            ],
        ]);

        $this->partialMock(ProjectBudgetSpecService::class, function ($mock)
        {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt): bool
                {
                    return str_contains($prompt, 'Landing corporativa con blog y formulario.')
                        && str_contains($prompt, 'Sumar app iOS nativa');
                })
                ->andReturn($this->fakeBudgetSpec());
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/regenerate-budget', [
                'note' => 'Sumar app iOS nativa',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.budget_given', 'Landing corporativa con blog y formulario.')
            ->assertJsonPath('data.data.estimate_notes.0.note', 'Sumar app iOS nativa')
            ->assertJsonPath('data.data.suggested_tasks.0.title', 'Diseño')
            ->assertJsonPath('data.status_id', ProjectStatus::STATUS_BUDGETED);
    }

    public function test_regenerate_budget_rejects_accepted_quote(): void
    {
        [$user, $team, $token, $client] = $this->adminWithToken();

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'name' => 'Accepted quote',
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_APPROVED,
            'data' => [
                'budget_given' => 'Landing original',
                'budget_preview_token' => 'acceptedToken'.bin2hex(random_bytes(8)),
                'budget_client_response' => ['status' => 'accepted'],
            ],
        ]);

        $this->partialMock(ProjectBudgetSpecService::class, function ($mock)
        {
            $mock->shouldReceive('generate')->never();
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/regenerate-budget', [
                'note' => 'Sumar app iOS nativa',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_from_brief_requires_client_and_text(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/from-brief', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['enterprise_id', 'business_name', 'contact_name', 'email', 'brief']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeBudgetSpec(): array
    {
        return [
            'ai_interpretation' => 'Landing corporativa',
            'dimension' => 'Small',
            'estimated_times' => '2 weeks',
            'resources' => '1 designer',
            'token_consumption' => [
                'notes' => 'Diseño: 400 K',
                'input_tokens' => 280000,
                'output_tokens' => 120000,
                'total_tokens' => 400000,
                'cost_euros' => 2,
                'savings_percent' => 57,
                'billable_euros' => 4.65,
                'currency' => 'EUR',
            ],
            'client_items' => [],
            'resource_breakdown' => [],
            'suggested_tasks' => [
                [
                    'title' => 'Diseño',
                    'description' => 'Home and contact.',
                    'category_name' => 'Diseño',
                    'estimated_hours' => 8,
                    'resource_level' => 'Senior',
                    'unit_price' => 800,
                    'estimated_tokens' => 400000,
                    'included' => true,
                ],
            ],
        ];
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
