<?php

namespace Tests\Feature\Api;

use App\Mail\ProjectBudgetQuoteMail;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Enterprise;
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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
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

            // The spec above carries no token_consumption, so the job derives it from the tasks.
            $mock->shouldReceive('buildTokenConsumption')
                ->once()
                ->andReturn([
                    'notes' => 'Discovery: 8h',
                    'input_tokens' => 5600,
                    'output_tokens' => 2400,
                    'total_tokens' => 8000,
                    'cost_euros' => 1.2,
                    'savings_percent' => 57.0,
                    'billable_euros' => 2.79,
                    'currency' => 'EUR',
                ]);
        });

        $response = $this->postJson('/api/projects/funnel/quote', [
            'name' => 'Ana',
            'surname' => 'García',
            'email' => 'ana.quote@example.com',
            'brief' => 'Necesito una web de reservas para un hotel con calendario y pagos.',
            'project_name' => 'Hotel bookings',
            'business_name' => 'Hotel Costa Azul',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('data.suggested_tasks.0.title', 'Discovery')
            ->assertJsonPath('data.suggested_tasks.0.estimated_hours', 8)
            ->assertJsonPath('data.suggested_tasks.0.resource_level', 'Senior')
            ->assertJsonPath('data.suggested_tasks.0.description', 'Análisis funcional y mapa de reservas')
            ->assertJsonMissingPath('data.suggested_tasks.0.unit_price');

        $this->assertNotEmpty($response->json('quote_token'));
        $this->assertSame($team->id, (int) config('projects.funnel_team_id'));

        $enterprise = Enterprise::withoutGlobalScopes()->where('email', 'ana.quote@example.com')->first();
        $this->assertNotNull($enterprise);
        $this->assertSame('Hotel Costa Azul', $enterprise->name);

        $project = Project::withoutGlobalScopes()->find($response->json('data.project_id'));
        $this->assertNotNull($project);
        $this->assertSame(1, (int) $project->status_id);
        $this->assertSame($enterprise->id, (int) $project->enterprise_id);
        $this->assertSame('Hotel bookings', $project->name);
        $this->assertSame('Web app for bookings', $project->data['ai_interpretation'] ?? null);
        $this->assertSame('ready', $project->data['funnel']['quote_status'] ?? null);
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

    public function test_strategy_tips_endpoint_returns_growth_framework(): void
    {
        $this->createFunnelTeam();

        $response = $this->getJson('/api/projects/funnel/strategy-tips');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Strategic Growth Framework')
            ->assertJsonPath('data.steps.0.number', 1)
            ->assertJsonPath('data.steps.0.title', 'tu dossier comercial.');

        $this->assertCount(12, $response->json('data.steps'));
        $this->assertNotEmpty($response->json('data.steps.0.tip'));
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
                    'business_name' => 'Hotel Costa Azul',
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
        Mail::fake();

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
            ->assertJsonPath('data.emailed', false)
            ->assertJsonMissingPath('data.price');

        $contact = Contact::withoutGlobalScopes()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Ana', $contact->name);

        $enterprise = Enterprise::withoutGlobalScopes()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($enterprise);

        $project = Project::withoutGlobalScopes()->find($response->json('data.project_id'));
        $this->assertNotNull($project);
        $this->assertSame(ProjectStatus::STATUS_BUDGET, (int) $project->status_id);
        $this->assertSame(2700.0, (float) $project->price);
        $this->assertSame(20.0, (float) $project->data['suggested_tasks'][1]['estimated_hours']);
        $this->assertArrayHasKey('unit_price', $project->data['suggested_tasks'][0]);
        $this->assertNotNull($project->board_id);
        Mail::assertNothingSent();
    }

    public function test_submit_sends_quote_email_when_sender_is_configured(): void
    {
        Mail::fake();

        $team = $this->createFunnelTeam();
        $team->setSetting('mail_from_name', 'Estimator', [
            'group' => 'email',
            'type' => 'text',
            'is_encrypted' => false,
        ]);
        $team->setSetting('mail_from_address', 'quotes@example.test', [
            'group' => 'email',
            'type' => 'email',
            'is_encrypted' => false,
        ]);

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
            'email' => 'ana.send@example.com',
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
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.emailed', true);

        $project = Project::withoutGlobalScopes()->find($response->json('data.project_id'));
        $this->assertNotNull($project);
        $this->assertSame(ProjectStatus::STATUS_AUTHORIZED, (int) $project->status_id);
        $this->assertSame('ana.send@example.com', data_get($project->data, 'budget_email.to_email'));
        $this->assertNotEmpty(data_get($project->data, 'budget_email.sent_at'));

        Mail::assertSent(ProjectBudgetQuoteMail::class, function (ProjectBudgetQuoteMail $mail): bool
        {
            return $mail->hasTo('ana.send@example.com')
                && $mail->hasFrom('quotes@example.test');
        });
    }

    public function test_lead_persists_intake_fields_without_surname(): void
    {
        $team = $this->createFunnelTeam();

        $response = $this->postJson('/api/projects/funnel/lead', [
            'name' => 'Victor Gómez',
            'email' => 'victor.intake@example.com',
            'phone' => '+34 600 111 222',
            'business_name' => 'Idoneo SL',
            'project_name' => 'CRM interno',
            'approx_users' => '20-30',
            'integrations' => 'WhatsApp, Stripe',
            'needed_by' => 'Octubre 2026',
            'location' => 'Madrid, España',
            'scope' => 'Panel para gestionar presupuestos y seguimiento de clientes.',
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'victor.intake@example.com')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('Victor Gómez', $contact->name);
        $this->assertSame('34600111222', (string) $contact->phone);
        $intake = (array) ($contact->data->intake ?? []);
        $this->assertSame('Idoneo SL', $intake['business_name'] ?? null);
        $this->assertSame('Madrid, España', $intake['location'] ?? null);
        $this->assertSame('20-30', $intake['approx_users'] ?? null);
    }

    public function test_quote_stores_intake_on_project_enterprise_and_contact(): void
    {
        $team = $this->createFunnelTeam();

        $this->mock(ProjectBudgetSpecService::class, function ($mock)
        {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'ai_interpretation' => 'CRM de presupuestos',
                    'dimension' => 'Small',
                    'estimated_times' => '4 weeks',
                    'resources' => '1 senior',
                    'client_items' => [],
                    'resource_breakdown' => [],
                    'suggested_tasks' => [
                        [
                            'title' => 'Discovery',
                            'category_name' => 'Análisis',
                            'estimated_hours' => 6,
                            'resource_level' => 'Senior',
                            'unit_price' => 800,
                            'included' => true,
                        ],
                    ],
                ]);

            $mock->shouldReceive('toClientSafe')
                ->once()
                ->andReturn([
                    'ai_interpretation' => 'CRM de presupuestos',
                    'dimension' => 'Small',
                    'estimated_times' => '4 weeks',
                    'resources' => '1 senior',
                    'suggested_tasks' => [
                        [
                            'title' => 'Discovery',
                            'description' => 'Alcance y mapa',
                            'category_name' => 'Análisis',
                            'estimated_hours' => 6,
                            'resource_level' => 'Senior',
                            'included' => true,
                        ],
                    ],
                ]);

            $mock->shouldReceive('buildTokenConsumption')
                ->once()
                ->andReturn([
                    'notes' => 'Discovery: 6h',
                    'input_tokens' => 1000,
                    'output_tokens' => 400,
                    'total_tokens' => 1400,
                    'cost_euros' => 0.4,
                    'savings_percent' => 57.0,
                    'billable_euros' => 0.9,
                    'currency' => 'EUR',
                ]);
        });

        $response = $this->postJson('/api/projects/funnel/quote', [
            'name' => 'Victor Gómez',
            'email' => 'victor.quote@example.com',
            'phone' => '+34 600 333 444',
            'business_name' => 'Idoneo SL',
            'project_name' => 'Estimator',
            'approx_users' => '15',
            'integrations' => 'Humano, Stripe',
            'needed_by' => 'Septiembre 2026',
            'location' => 'Valencia',
            'scope' => 'App para cotizar proyectos a medida.',
            'brief' => "Proyecto: Estimator\nEmpresa: Idoneo SL\nQué tiene que hacer: App para cotizar proyectos a medida.",
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'ready');

        $contact = Contact::withoutGlobalScopes()->where('email', 'victor.quote@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame('34600333444', (string) $contact->phone);

        $enterprise = Enterprise::withoutGlobalScopes()->where('email', 'victor.quote@example.com')->first();
        $this->assertNotNull($enterprise);
        $this->assertSame('Idoneo SL', $enterprise->name);
        $this->assertSame('Valencia', $enterprise->locality);

        $project = Project::withoutGlobalScopes()->find($response->json('data.project_id'));
        $this->assertNotNull($project);
        $this->assertSame('Estimator', $project->name);
        $this->assertSame('15', $project->data['funnel']['intake']['approx_users'] ?? null);
        $this->assertSame('Humano, Stripe', $project->data['funnel']['intake']['integrations'] ?? null);
        $this->assertSame('Septiembre 2026', $project->data['funnel']['intake']['needed_by'] ?? null);
        $this->assertSame($team->id, (int) $project->team_id);
    }

    public function test_chat_prompt_requires_authentication(): void
    {
        $this->createFunnelTeam();

        $this->getJson('/api/projects/funnel/chat-prompt')->assertUnauthorized();
        $this->putJson('/api/projects/funnel/chat-prompt', [
            'prompt_instruction' => 'Pregunta solo por el presupuesto, las horas y el alcance.',
        ])->assertUnauthorized();
    }

    public function test_chat_prompt_forbidden_when_user_is_not_on_token_team(): void
    {
        [, $plain] = $this->createTeamWithApiToken();
        $outsider = User::factory()->withPersonalTeam()->create();
        $token = $outsider->createToken('estimator-prompt')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Team-Token' => $plain,
        ])->getJson('/api/projects/funnel/chat-prompt')->assertForbidden();
    }

    public function test_lead_is_stored_on_the_team_from_frontend_token(): void
    {
        config(['projects.funnel_team_id' => null]);
        [$team, $plain] = $this->createTeamWithApiToken();

        $this->withHeader('X-Team-Token', $plain)
            ->postJson('/api/projects/funnel/lead', [
                'name' => 'Ana',
                'surname' => 'García',
                'email' => 'ana.token@example.com',
            ])
            ->assertCreated();

        $contact = Contact::withoutGlobalScopes()
            ->where('email', 'ana.token@example.com')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame($team->id, (int) $contact->team_id);
    }

    public function test_two_frontends_can_send_leads_to_different_teams(): void
    {
        config(['projects.funnel_team_id' => null]);
        [$teamA, $tokenA] = $this->createTeamWithApiToken();
        [$teamB, $tokenB] = $this->createTeamWithApiToken();

        $this->withHeader('X-Team-Token', $tokenA)
            ->postJson('/api/projects/funnel/lead', [
                'name' => 'Ana',
                'email' => 'ana.team-a@example.com',
            ])
            ->assertCreated();

        $this->withHeader('X-Team-Token', $tokenB)
            ->postJson('/api/projects/funnel/lead', [
                'name' => 'Luis',
                'email' => 'luis.team-b@example.com',
            ])
            ->assertCreated();

        $this->assertSame(
            $teamA->id,
            (int) Contact::withoutGlobalScopes()->where('email', 'ana.team-a@example.com')->value('team_id'),
        );
        $this->assertSame(
            $teamB->id,
            (int) Contact::withoutGlobalScopes()->where('email', 'luis.team-b@example.com')->value('team_id'),
        );
    }

    public function test_invalid_team_token_is_rejected(): void
    {
        config(['projects.funnel_team_id' => null]);

        $this->withHeader('X-Team-Token', 'not-a-valid-team-token')
            ->postJson('/api/projects/funnel/lead', [
                'name' => 'Ana',
                'email' => 'ana.invalid@example.com',
            ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid team token.');
    }

    public function test_funnel_member_can_get_and_update_chat_prompt(): void
    {
        $team = $this->createFunnelTeam();
        $user = $team->owner;
        $this->assertNotNull($user);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $token = $user->createToken('estimator-prompt')->plainTextToken;

        $get = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/funnel/chat-prompt')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertStringContainsString('{lead_name}', (string) $get->json('data.prompt_instruction'));

        $custom = 'Eres un presupuestador. El cliente es {lead_name}. Pregunta solo por horas y precio. Requisitos: {requirements_json}';

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/funnel/chat-prompt', [
                'prompt_instruction' => $custom,
            ])
            ->assertOk()
            ->assertJsonPath('data.prompt_instruction', $custom);

        $this->assertSame(
            $custom,
            app(ProjectBudgetSpecService::class)->resolveBudgetChatPrompt($team),
        );
    }

    public function test_funnel_member_can_get_and_update_quote_sender(): void
    {
        $team = $this->createFunnelTeam();
        $user = $team->owner;
        $this->assertNotNull($user);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $token = $user->createToken('estimator-sender')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/funnel/sender')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.can_send', false)
            ->assertJsonPath('data.required_include', 'include:spf.revisionalpha.com');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/funnel/sender', [
                'mail_from_name' => 'Presupuestos Acme',
                'mail_from_address' => 'presupuestos@acme.test',
            ])
            ->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.from_name', 'Presupuestos Acme')
            ->assertJsonPath('data.from_address', 'presupuestos@acme.test')
            ->assertJsonPath('data.spf.domain', 'acme.test');

        $this->assertSame('Presupuestos Acme', $team->fresh()->getSetting('mail_from_name'));
        $this->assertSame('presupuestos@acme.test', $team->fresh()->getSetting('mail_from_address'));
    }

    public function test_quote_sender_requires_authentication(): void
    {
        $this->createFunnelTeam();

        $this->getJson('/api/projects/funnel/sender')->assertUnauthorized();
        $this->putJson('/api/projects/funnel/sender', [
            'mail_from_name' => 'Presupuestos',
            'mail_from_address' => 'quotes@example.test',
        ])->assertUnauthorized();
    }

    public function test_funnel_member_can_get_and_update_token_pricing(): void
    {
        $team = $this->createFunnelTeam();
        $user = $team->owner;
        $this->assertNotNull($user);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $token = $user->createToken('estimator-tokens')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/funnel/token-pricing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.input_rate', 11)
            ->assertJsonPath('data.output_rate', 55)
            ->assertJsonPath('data.discriminate', true)
            ->assertJsonPath('data.can_update', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/funnel/token-pricing', [
                'input_rate' => 3.5,
                'output_rate' => 12,
                'discriminate' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.input_rate', 3.5)
            ->assertJsonPath('data.output_rate', 12)
            ->assertJsonPath('data.discriminate', false);

        $fresh = $team->fresh();
        $this->assertSame('3.5', (string) $fresh->getSetting(ProjectBudgetSpecService::SETTING_TOKEN_INPUT_RATE));
        $this->assertSame('12', (string) $fresh->getSetting(ProjectBudgetSpecService::SETTING_TOKEN_OUTPUT_RATE));
        $this->assertFalse(filter_var($fresh->getSetting(ProjectBudgetSpecService::SETTING_TOKEN_DISCRIMINATE), FILTER_VALIDATE_BOOLEAN));
    }

    public function test_token_pricing_requires_authentication(): void
    {
        $this->createFunnelTeam();

        $this->getJson('/api/projects/funnel/token-pricing')->assertUnauthorized();
        $this->putJson('/api/projects/funnel/token-pricing', [
            'input_rate' => 3,
            'output_rate' => 10,
            'discriminate' => true,
        ])->assertUnauthorized();
    }

    public function test_chat_uses_team_budget_chat_prompt(): void
    {
        $team = $this->createFunnelTeam();
        $service = app(ProjectBudgetSpecService::class);
        $prompt = $service->ensureBudgetChatPrompt($team);
        $prompt->forceFill([
            'prompt_instruction' => 'PROMPT PERSONALIZADO para {lead_name}. JSON: {requirements_json}',
        ])->save();

        $resolved = $service->resolveBudgetChatPrompt($team);
        $this->assertStringContainsString('PROMPT PERSONALIZADO', $resolved);

        $interpolated = $service->interpolateBudgetChatPrompt(
            $resolved,
            'Victor',
            '[{"key":"objetivo"}]',
            'Estimator',
            'Empresa: Idoneo',
        );
        $this->assertStringContainsString('Victor', $interpolated);
        $this->assertStringNotContainsString('{lead_name}', $interpolated);
    }

    public function test_public_chat_passes_funnel_team_to_service(): void
    {
        $team = $this->createFunnelTeam();
        $teamId = (int) $team->id;

        $this->mock(ProjectBudgetSpecService::class, function ($mock) use ($teamId)
        {
            $mock->shouldReceive('chatTurn')
                ->once()
                ->withArgs(function ($messages, $projectName, $leadName, $passedTeam, $context) use ($teamId)
                {
                    return $passedTeam instanceof \App\Models\Team
                        && (int) $passedTeam->id === $teamId
                        && $leadName === 'Ana'
                        && $context === 'Empresa: Idoneo';
                })
                ->andReturn([
                    'assistant_message' => '¿Cuál es el objetivo?',
                    'requirements' => [],
                    'brief' => 'Brief',
                    'project_name' => null,
                    'business_name' => null,
                    'all_met' => false,
                ]);
        });

        $this->postJson('/api/projects/funnel/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Quiero un CRM'],
            ],
            'lead_name' => 'Ana',
            'project_name' => 'CRM',
            'context' => 'Empresa: Idoneo',
        ])->assertOk()->assertJsonPath('data.assistant_message', '¿Cuál es el objetivo?');
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

    /**
     * @return array{0: \App\Models\Team, 1: string}
     */
    private function createTeamWithApiToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $plain = $team->createApiToken('Frontend funnel', '*')['plain'];

        return [$team, $plain];
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
