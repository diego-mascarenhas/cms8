<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\ProjectStatus;
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
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectCloudAgentTest extends TestCase
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
    }

    public function test_cloud_agent_requires_a_repository(): void
    {
        [, $token, $project] = $this->project();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/cloud-agent', [
                'prompt' => 'Cambia el botón.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'repository_required');
    }

    public function test_cloud_agent_saves_the_repository_and_asks_for_an_idoneo_branch(): void
    {
        [, $token, $project] = $this->project();
        config(['services.cursor.api_key' => 'test-key']);

        Http::fake([
            'https://api.cursor.com/v1/agents' => Http::response([
                'agent' => [
                    'id' => 'bc-test',
                    'url' => 'https://cursor.com/agents/bc-test',
                    'name' => 'IDONEO Board Project',
                ],
                'run' => [
                    'id' => 'run-test',
                    'status' => 'CREATING',
                ],
            ], 200),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/cloud-agent', [
                'prompt' => 'Cambia el botón.',
                'repository_url' => 'https://github.com/acme/web',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', 'bc-test')
            ->assertJsonPath('data.url', 'https://cursor.com/agents/bc-test')
            ->assertJsonPath('data.branch', 'idoneo/board-project')
            ->assertJsonPath('data.repository_url', 'https://github.com/acme/web');

        Http::assertSent(function ($request): bool
        {
            $body = $request->data();

            return $request->url() === 'https://api.cursor.com/v1/agents'
                && str_contains($body['prompt']['text'], 'idoneo/board-project')
                && str_contains($body['prompt']['text'], 'Cambia el botón.')
                && $body['repos'][0]['url'] === 'https://github.com/acme/web'
                && $body['name'] === 'IDONEO Board Project';
        });

        $this->assertSame(
            'https://github.com/acme/web',
            data_get($project->fresh()->data, 'cursor_repository'),
        );
    }

    public function test_project_edit_saves_the_repository_url(): void
    {
        [, $token, $project] = $this->project();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/'.$project->id, [
                'name' => 'Board Project',
                'repository_url' => 'https://github.com/diego-mascarenhas/mi.actibiz/',
            ])
            ->assertOk()
            ->assertJsonPath('data.repository_url', 'https://github.com/diego-mascarenhas/mi.actibiz');

        $this->assertSame(
            'https://github.com/diego-mascarenhas/mi.actibiz',
            data_get($project->fresh()->data, 'cursor_repository'),
        );
    }

    public function test_approved_project_can_save_the_repository_without_changing_the_budget(): void
    {
        [, $token, $project] = $this->project();
        $project->forceFill([
            'status_id' => ProjectStatus::STATUS_APPROVED,
            'price' => 1200,
            'data' => [
                'budget_given' => 'Landing',
                'quote_amount' => 1200,
                'budget_client_response' => ['status' => 'accepted'],
            ],
        ])->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/'.$project->id, [
                'name' => 'Board Project',
                'description' => 'Proyecto en curso',
                'enterprise_id' => $project->enterprise_id,
                'responsible_id' => $project->responsible_id,
                'repository_url' => 'https://github.com/diego-mascarenhas/app.fanyion.git',
            ])
            ->assertOk()
            ->assertJsonPath('data.repository_url', 'https://github.com/diego-mascarenhas/app.fanyion')
            ->assertJsonPath('data.description', 'Proyecto en curso');

        $fresh = $project->fresh();
        $this->assertSame('https://github.com/diego-mascarenhas/app.fanyion', data_get($fresh->data, 'cursor_repository'));
        $this->assertSame('accepted', data_get($fresh->data, 'budget_client_response.status'));
        $this->assertSame(1200, data_get($fresh->data, 'quote_amount'));
        $this->assertSame(1200.0, (float) $fresh->price);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/projects/'.$project->id, [
                'data' => ['quote_amount' => 1],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Este presupuesto aprobado ya no se puede editar. El importe y el detalle del presupuesto quedan fijos.');

        $this->assertSame(1200, data_get($project->fresh()->data, 'quote_amount'));
    }

    public function test_cloud_agent_shows_the_cursor_error(): void
    {
        [, $token, $project] = $this->project();
        config(['services.cursor.api_key' => 'test-key']);

        Http::fake([
            'https://api.cursor.com/v1/agents' => Http::response([
                'error' => [
                    'code' => 'repository_access',
                    'message' => 'Repository not found',
                ],
            ], 400),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/cloud-agent', [
                'prompt' => 'Cambia el botón.',
                'repository_url' => 'https://github.com/diego-mascarenhas/mi.actibiz/',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Cursor no puede leer ese repositorio. Revisa la URL y que la cuenta de la clave tenga GitHub conectado con acceso a él.');

        Http::assertSent(function ($request): bool
        {
            return $request->data()['repos'][0]['url'] === 'https://github.com/diego-mascarenhas/mi.actibiz';
        });
    }

    public function test_cloud_agent_rejects_a_grok_key_response(): void
    {
        [, $token, $project] = $this->project();
        config(['services.cursor.api_key' => 'test-key']);

        Http::fake([
            'https://api.cursor.com/v1/agents' => Http::response([
                'error' => [
                    'code' => 'key_not_supported',
                    'message' => 'This API key is not supported.',
                ],
            ], 403),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/cloud-agent', [
                'prompt' => 'Cambia el botón.',
                'repository_url' => 'https://github.com/acme/web',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Esa clave no sirve para los agentes de Cursor. Crea una clave de usuario en cursor.com/dashboard, en API Keys. Las que empiezan por grok_ no están admitidas.');
    }

    public function test_cloud_agent_status_reports_processing_and_then_done(): void
    {
        [, $token, $project] = $this->project();
        config(['services.cursor.api_key' => 'test-key']);

        Http::fake([
            'https://api.cursor.com/v1/agents' => Http::response([
                'agent' => [
                    'id' => 'bc-test',
                    'url' => 'https://cursor.com/agents/bc-test',
                ],
            ], 200),
            'https://api.cursor.com/v1/agents/bc-test' => Http::sequence()
                ->push([
                    'id' => 'bc-test',
                    'status' => 'ACTIVE',
                    'url' => 'https://cursor.com/agents/bc-test',
                    'latestRunId' => 'run-test',
                ], 200)
                ->push([
                    'id' => 'bc-test',
                    'status' => 'IDLE',
                    'url' => 'https://cursor.com/agents/bc-test',
                    'latestRunId' => 'run-test',
                ], 200),
            'https://api.cursor.com/v1/agents/bc-test/runs/run-test' => Http::response([
                'id' => 'run-test',
                'status' => 'FINISHED',
                'result' => 'El botón ya está. No cambié ningún archivo.',
            ], 200),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/cloud-agent', [
                'prompt' => 'Cambia el botón.',
                'repository_url' => 'https://github.com/acme/web',
            ])
            ->assertOk()
            ->assertJsonPath('data.phase', 'processing');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/cloud-agent')
            ->assertOk()
            ->assertJsonPath('data.phase', 'processing')
            ->assertJsonPath('data.branch', 'idoneo/board-project');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/cloud-agent')
            ->assertOk()
            ->assertJsonPath('data.phase', 'done')
            ->assertJsonPath('data.result', 'El botón ya está. No cambié ningún archivo.');

        $this->assertSame('done', data_get($project->fresh()->data, 'cursor_agent.phase'));
        $this->assertSame(
            'El botón ya está. No cambié ningún archivo.',
            data_get($project->fresh()->data, 'cursor_agent.result'),
        );
    }

    public function test_cloud_agent_moves_todo_tasks_and_records_the_result(): void
    {
        $this->seed(TaskStatusSeeder::class);
        [$user, $token, $project] = $this->project();
        config(['services.cursor.api_key' => 'test-key']);

        $todo = TaskStatus::query()->where('name', 'TO_DO')->firstOrFail();
        $task = Task::withoutGlobalScopes()->create([
            'team_id' => $project->team_id,
            'board_id' => $project->board_id,
            'responsible_id' => $user->id,
            'title' => 'Whatsapp',
            'description' => 'Ver el botón de invitación.',
            'status_id' => $todo->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'order' => 1,
        ]);

        Http::fake([
            'https://api.cursor.com/v1/agents' => Http::response([
                'agent' => [
                    'id' => 'bc-test',
                    'url' => 'https://cursor.com/agents/bc-test',
                ],
            ], 200),
            'https://api.cursor.com/v1/agents/bc-test' => Http::sequence()
                ->push(['id' => 'bc-test', 'status' => 'ACTIVE', 'latestRunId' => 'run-test'], 200)
                ->push(['id' => 'bc-test', 'status' => 'IDLE', 'latestRunId' => 'run-test'], 200),
            'https://api.cursor.com/v1/agents/bc-test/runs/run-test' => Http::response([
                'id' => 'run-test',
                'status' => 'FINISHED',
                'result' => 'El botón ya está. No cambié ningún archivo.',
            ], 200),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/cloud-agent', [
                'prompt' => 'Implementa las tareas.',
                'repository_url' => 'https://github.com/acme/web',
                'task_ids' => [$task->id],
            ])
            ->assertOk();

        $inProgress = TaskStatus::query()->where('name', 'IN_PROGRESS')->firstOrFail();
        $this->assertSame($inProgress->id, $task->fresh()->status_id);
        $this->assertTrue(
            Time::withoutGlobalScopes()->where('task_id', $task->id)->whereNull('end_time')->exists(),
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/cloud-agent')
            ->assertOk()
            ->assertJsonPath('data.phase', 'processing');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/cloud-agent')
            ->assertOk()
            ->assertJsonPath('data.phase', 'done')
            ->assertJsonPath('data.result', 'El botón ya está. No cambié ningún archivo.');

        $review = TaskStatus::query()->where('name', 'REVIEW')->firstOrFail();
        $this->assertSame($review->id, $task->fresh()->status_id);
        $time = Time::withoutGlobalScopes()->where('task_id', $task->id)->first();
        $this->assertNotNull($time?->end_time);
        $this->assertStringContainsString('El botón ya está. No cambié ningún archivo.', (string) $time->description);
    }

    public function test_cloud_agent_status_is_empty_before_launch(): void
    {
        [, $token, $project] = $this->project();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id.'/cloud-agent')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_cloud_agent_reports_a_missing_api_key(): void
    {
        [, $token, $project] = $this->project();
        config(['services.cursor.api_key' => '']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/cloud-agent', [
                'prompt' => 'Cambia el botón.',
                'repository_url' => 'https://github.com/acme/web',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Falta la clave de Cursor en el servidor.');
    }

    /**
     * @return array{0: User, 1: string, 2: Project}
     */
    private function project(): array
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
            'name' => 'Board Client',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $board = TaskBoard::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Project board',
            'description' => 'Test board',
            'is_default' => false,
            'order' => 0,
        ]);

        $project = Project::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'board_id' => $board->id,
            'name' => 'Board Project',
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        return [$user, $user->createToken('cloud-agent')->plainTextToken, $project];
    }
}
