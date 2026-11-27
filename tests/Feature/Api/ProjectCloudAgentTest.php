<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\TaskBoard;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
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
