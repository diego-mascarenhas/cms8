<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectBudgetSpecService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class ProjectFunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);
    }

    public function test_lead_is_captured_on_first_step(): void
    {
        $team = $this->createFunnelTeam();

        $response = $this->postJson('/api/projects/funnel/lead', [
            'name' => 'Ana',
            'surname' => 'García',
            'email' => 'ana.lead@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'ana.lead@example.com')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('Ana', $contact->name);
        $this->assertSame('García', $contact->surname);
        $this->assertSame('Lead', optional(ContactStatus::find($contact->status_id))->name);
    }

    public function test_quote_returns_tasks_without_prices(): void
    {
        $team = $this->createFunnelTeam();

        $this->mock(ProjectBudgetSpecService::class, function ($mock)
        {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'ai_interpretation' => 'Web app for bookings',
                    'dimension' => 'Medium',
                    'estimated_times' => '6-8 weeks',
                    'resources' => '1 senior + 1 junior',
                    'client_items' => [],
                    'resource_breakdown' => [],
                    'suggested_tasks' => [
                        [
                            'title' => 'Discovery',
                            'category_name' => 'Análisis',
                            'estimated_hours' => 8,
                            'resource_level' => 'Senior',
                            'unit_price' => 1200,
                            'included' => true,
                        ],
                    ],
                ]);

            $mock->shouldReceive('toClientSafe')
                ->once()
                ->andReturn([
                    'ai_interpretation' => 'Web app for bookings',
                    'dimension' => 'Medium',
                    'estimated_times' => '6-8 weeks',
                    'resources' => '1 senior + 1 junior',
                    'suggested_tasks' => [
                        [
                            'title' => 'Discovery',
                            'description' => 'Análisis funcional y mapa de reservas',
                            'category_name' => 'Análisis',
                            'estimated_hours' => 8,
                            'resource_level' => 'Senior',
                            'included' => true,
                        ],
                    ],
                ]);
        });

        $response = $this->postJson('/api/projects/funnel/quote', [
            'name' => 'Ana',
            'surname' => 'García',
            'email' => 'ana.quote@example.com',
            'brief' => 'Necesito una web de reservas para un hotel con calendario y pagos.',
            'project_name' => 'Hotel bookings',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.suggested_tasks.0.title', 'Discovery')
            ->assertJsonPath('data.suggested_tasks.0.estimated_hours', 8)
            ->assertJsonPath('data.suggested_tasks.0.resource_level', 'Senior')
            ->assertJsonPath('data.suggested_tasks.0.description', 'Análisis funcional y mapa de reservas')
            ->assertJsonMissingPath('data.suggested_tasks.0.unit_price');

        $this->assertNotEmpty($response->json('quote_token'));
        $this->assertSame($team->id, (int) config('projects.funnel_team_id'));

        $enterprise = Enterprise::withoutGlobalScopes()->where('email', 'ana.quote@example.com')->first();
        $this->assertNotNull($enterprise);

        $project = Project::withoutGlobalScopes()->find($response->json('data.project_id'));
        $this->assertNotNull($project);
        $this->assertSame(1, (int) $project->status_id);
        $this->assertSame($enterprise->id, (int) $project->enterprise_id);
        $this->assertSame('Hotel bookings', $project->name);
        $this->assertSame('Web app for bookings', $project->data['ai_interpretation'] ?? null);
    }

    public function test_requirements_endpoint_returns_tech_checklist(): void
    {
        $this->createFunnelTeam();

        $response = $this->getJson('/api/projects/funnel/requirements');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requirements.0.key', 'objetivo')
            ->assertJsonPath('data.requirements.0.met', false);

        $keys = collect($response->json('data.requirements'))->pluck('key')->all();
        $this->assertSame(
            ['objetivo', 'negocio', 'usuarios', 'funcionalidades', 'plataforma', 'urls', 'diseno', 'alcance'],
            $keys,
        );
    }

    public function test_guide_endpoint_returns_evaluated_requirements(): void
    {
        $this->createFunnelTeam();

        $this->mock(ProjectBudgetSpecService::class, function ($mock)
        {
            $mock->shouldReceive('guideBrief')
                ->once()
                ->andReturn([
                    'requirements' => [
                        [
                            'key' => 'objetivo',
                            'name' => 'Objetivo',
                            'hint' => 'Qué problema resuelve',
                            'met' => true,
                            'feedback' => 'Cubierto',
                        ],
                    ],
                    'summary' => 'Buen punto de partida',
                    'suggested_additions' => 'Añade plataforma',
                    'improved_brief' => 'Necesito una web…',
                    'all_met' => false,
                ]);
        });

        $response = $this->postJson('/api/projects/funnel/guide', [
            'brief' => 'Necesito una web de reservas para un hotel.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requirements.0.met', true)
            ->assertJsonPath('data.summary', 'Buen punto de partida');
    }

    public function test_chat_endpoint_returns_assistant_turn_and_checklist(): void
    {
        $this->createFunnelTeam();

        $this->mock(ProjectBudgetSpecService::class, function ($mock)
        {
            $mock->shouldReceive('chatTurn')
                ->once()
                ->andReturn([
                    'assistant_message' => '¿Para quiénes sería esta plataforma?',
                    'requirements' => [
                        [
                            'key' => 'objetivo',
                            'name' => 'Objetivo',
                            'hint' => 'Qué problema resuelve',
                            'met' => true,
                            'feedback' => 'Cubierto',
                        ],
                    ],
                    'brief' => 'Web de reservas para hotel',
                    'project_name' => 'Reservas hotel',
                    'all_met' => false,
                ]);
        });

        $response = $this->postJson('/api/projects/funnel/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Necesito una web de reservas para un hotel'],
            ],
            'lead_name' => 'Ana',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assistant_message', '¿Para quiénes sería esta plataforma?')
            ->assertJsonPath('data.requirements.0.met', true)
            ->assertJsonPath('data.brief', 'Web de reservas para hotel');
    }

    public function test_chat_welcome_without_messages_uses_lead_name(): void
    {
        $this->createFunnelTeam();

        $response = $this->postJson('/api/projects/funnel/chat', [
            'messages' => [],
            'lead_name' => 'Ana',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.all_met', false);

        $this->assertStringContainsString('Ana', (string) $response->json('data.assistant_message'));
        $this->assertNotEmpty($response->json('data.requirements'));
    }

    public function test_submit_creates_contact_enterprise_and_project_with_internal_prices(): void
    {
        $team = $this->createFunnelTeam();

        $spec = [
            'ai_interpretation' => 'Landing + CMS',
            'dimension' => 'Small',
            'estimated_times' => '3 weeks',
            'resources' => '1 developer',
            'client_items' => [],
            'resource_breakdown' => [],
            'suggested_tasks' => [
                [
                    'title' => 'Diseño',
                    'category_name' => 'Diseño',
                    'estimated_hours' => 12,
                    'resource_level' => 'Senior',
                    'unit_price' => 900,
                    'included' => true,
                ],
                [
                    'title' => 'Implementación',
                    'category_name' => 'Desarrollo',
                    'estimated_hours' => 24,
                    'resource_level' => 'Junior',
                    'unit_price' => 1800,
                    'included' => true,
                ],
            ],
        ];

        $quoteToken = Crypt::encryptString(json_encode([
            'team_id' => $team->id,
            'brief' => 'Landing corporativa con blog y formulario de contacto.',
            'project_name' => 'Landing ACME',
            'spec' => $spec,
            'created_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        $this->mock(ProjectBudgetSpecService::class, function ($mock) use ($spec)
        {
            $mock->shouldReceive('mergeClientTaskEdits')
                ->once()
                ->andReturnUsing(function (array $cached, array $clientTasks) use ($spec)
                {
                    $service = new ProjectBudgetSpecService;

                    return $service->mergeClientTaskEdits($spec, $clientTasks);
                });

            $mock->shouldReceive('toClientSafe')->never();
        });

        $response = $this->postJson('/api/projects/funnel/submit', [
            'name' => 'Ana',
            'surname' => 'García',
            'email' => 'ana@example.com',
            'brief' => 'Landing corporativa con blog y formulario de contacto.',
            'project_name' => 'Landing ACME',
            'quote_token' => $quoteToken,
            'suggested_tasks' => [
                [
                    'title' => 'Diseño',
                    'category_name' => 'Diseño',
                    'estimated_hours' => 12,
                    'included' => true,
                ],
                [
                    'title' => 'Implementación',
                    'category_name' => 'Desarrollo',
                    'estimated_hours' => 20,
                    'included' => true,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project_name', 'Landing ACME')
            ->assertJsonPath('data.tasks_count', 2)
            ->assertJsonMissingPath('data.price');

        $contact = Contact::withoutGlobalScopes()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Ana', $contact->name);

        $enterprise = Enterprise::withoutGlobalScopes()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($enterprise);

        $project = Project::withoutGlobalScopes()->find($response->json('data.project_id'));
        $this->assertNotNull($project);
        $this->assertSame(1, (int) $project->status_id);
        $this->assertSame(2700.0, (float) $project->price);
        $this->assertSame(20.0, (float) $project->data['suggested_tasks'][1]['estimated_hours']);
        $this->assertArrayHasKey('unit_price', $project->data['suggested_tasks'][0]);
        $this->assertNotNull($project->board_id);
    }

    public function test_quote_fails_when_funnel_team_not_configured(): void
    {
        config(['projects.funnel_team_id' => null]);

        $this->postJson('/api/projects/funnel/quote', [
            'name' => 'Ana',
            'surname' => 'García',
            'email' => 'ana@example.com',
            'brief' => 'Necesito una web de reservas para un hotel con calendario y pagos.',
        ])->assertStatus(503);
    }

    public function test_quote_persists_draft_project_even_when_ai_fails(): void
    {
        $team = $this->createFunnelTeam();

        $this->mock(ProjectBudgetSpecService::class, function ($mock)
        {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(new \RuntimeException('AI unavailable'));
        });

        $response = $this->postJson('/api/projects/funnel/quote', [
            'name' => 'Luis',
            'surname' => 'Pérez',
            'email' => 'luis.draft@example.com',
            'brief' => 'Necesito una web corporativa responsive para mi negocio.',
            'project_name' => 'Web Luis',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'AI unavailable');

        $projectId = (int) $response->json('data.project_id');
        $this->assertGreaterThan(0, $projectId);

        $project = Project::withoutGlobalScopes()->find($projectId);
        $this->assertNotNull($project);
        $this->assertSame($team->id, (int) $project->team_id);
        $this->assertSame(1, (int) $project->status_id);
        $this->assertSame('Web Luis', $project->name);
        $this->assertStringContainsString('web corporativa', (string) ($project->data['budget_given'] ?? ''));
    }

    private function createFunnelTeam(): \App\Models\Team
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        config(['projects.funnel_team_id' => $team->id]);

        return $team;
    }
}
