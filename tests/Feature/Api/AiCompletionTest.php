<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\TokenUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_token_can_complete_ai_and_logs_module_usage(): void
    {
        AnonymousAgent::fake(['Suggested proposal text.']);

        $user = User::factory()->withPersonalTeam()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        $team = $user->currentTeam;
        $created = $team->createApiToken('Fanyion', '*');

        $module = Module::query()->firstOrCreate(
            ['key' => 'proposals'],
            [
                'name' => 'Proposals',
                'is_core' => false,
                'group' => 'innovation',
                'order' => 1,
                'status' => 1,
            ],
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$created['plain'])
            ->postJson('/api/ai/complete', [
                'module' => 'proposals',
                'prompt' => 'Write a short innovation proposal title.',
                'service' => 'AIAssistanceService',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.text', 'Suggested proposal text.');
        $response->assertJsonPath('data.module', 'proposals');
        $response->assertJsonPath('data.service', 'AIAssistanceService');
        $this->assertGreaterThan(0, (int) $response->json('data.usage.total_tokens'));

        $this->assertDatabaseHas('token_usage_logs', [
            'team_id' => $team->id,
            'module_id' => $module->id,
            'service' => 'AIAssistanceService',
        ]);

        $log = TokenUsageLog::query()->where('team_id', $team->id)->first();
        $this->assertNotNull($log);
        $this->assertGreaterThan(0, (int) $log->json_tokens);
    }

    public function test_website_module_logs_token_usage_for_content_site_chat(): void
    {
        AnonymousAgent::fake(['{"summary":"Título nuevo","files":[]}']);

        $user = User::factory()->withPersonalTeam()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');

        $team = $user->currentTeam;
        $created = $team->createApiToken('Content', '*');

        $module = Module::query()->firstOrCreate(
            ['key' => 'website'],
            [
                'name' => 'Sitio web',
                'is_core' => false,
                'group' => 'content',
                'order' => 3,
                'status' => 1,
            ],
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$created['plain'])
            ->postJson('/api/ai/complete', [
                'module' => 'website',
                'prompt' => 'Cambiá el título de la portada.',
                'service' => 'ContentSiteChat',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.module', 'website');
        $response->assertJsonPath('data.service', 'ContentSiteChat');
        $this->assertGreaterThan(0, (int) $response->json('data.usage.total_tokens'));

        $this->assertDatabaseHas('token_usage_logs', [
            'team_id' => $team->id,
            'module_id' => $module->id,
            'service' => 'ContentSiteChat',
        ]);
    }

    public function test_complete_rejects_unknown_module(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');
        $created = $user->currentTeam->createApiToken('Fanyion', '*');

        $this->withHeader('Authorization', 'Bearer '.$created['plain'])
            ->postJson('/api/ai/complete', [
                'module' => 'unknown_module',
                'prompt' => 'Hello',
            ])
            ->assertStatus(422);
    }

    public function test_complete_requires_authentication(): void
    {
        $this->postJson('/api/ai/complete', [
            'module' => 'proposals',
            'prompt' => 'Hello',
        ])->assertStatus(401);
    }

    public function test_complete_stores_toon_savings_without_a_model_column(): void
    {
        AnonymousAgent::fake(['Suggested proposal text.']);

        $user = User::factory()->withPersonalTeam()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');
        $created = $user->currentTeam->createApiToken('Fanyion', '*');

        $this->withHeader('Authorization', 'Bearer '.$created['plain'])
            ->postJson('/api/ai/complete', [
                'module' => 'proposals',
                'prompt' => 'Write a short innovation proposal title.',
                'service' => 'ClaudeProposalValidator',
                'model' => 'claude-3-5-sonnet-20241022',
                'toon' => [
                    'used_toon' => true,
                    'tokens_saved' => 20,
                    'json_size' => 400,
                    'toon_size' => 250,
                ],
            ])
            ->assertOk();

        $log = TokenUsageLog::query()->where('service', 'ClaudeProposalValidator')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->used_toon);
        $this->assertGreaterThan((int) $log->toon_tokens, (int) $log->json_tokens);
        $this->assertFalse(isset($log->model));
    }

    public function test_usage_endpoint_records_fallback_tokens(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Role::findOrCreate('admin');
        $user->assignRole('admin');
        $created = $user->currentTeam->createApiToken('Fanyion', '*');

        $this->withHeader('Authorization', 'Bearer '.$created['plain'])
            ->postJson('/api/ai/usage', [
                'module' => 'proposals',
                'service' => 'ClaudeProposalValidator',
                'model' => 'claude-sonnet-4-5-20250929',
                'prompt_tokens' => 80,
                'completion_tokens' => 20,
                'total_tokens' => 100,
                'toon' => [
                    'used_toon' => true,
                    'tokens_saved' => 15,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.service', 'ClaudeProposalValidator')
            ->assertJsonPath('data.usage.total_tokens', 100);

        $log = TokenUsageLog::query()->where('service', 'ClaudeProposalValidator')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->used_toon);
        $this->assertSame(100, (int) $log->toon_tokens);
        $this->assertSame(115, (int) $log->json_tokens);
    }
}
