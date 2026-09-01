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
}
