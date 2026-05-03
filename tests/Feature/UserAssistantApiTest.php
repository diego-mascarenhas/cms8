<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AssistantChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class UserAssistantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_chat_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/assistant/chat', [
            'message' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_assistant_chat_returns_422_when_user_has_no_current_team(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->create([
            'current_team_id' => null,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/chat', [
                'message' => 'Hello',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_assistant_chat_returns_json_when_authenticated_with_team(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->mock(AssistantChatService::class, function ($mock)
        {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([
                    'response' => 'Mocked assistant reply',
                    'routed_to' => 'Test flow',
                    'usage' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                ]);
        });

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/chat', [
                'message' => 'Hello team',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'response' => 'Mocked assistant reply',
            'routed_to' => 'Test flow',
        ]);
    }

    public function test_assistant_chat_blank_prompt_key_uses_default_router_argument(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->mock(AssistantChatService::class, function ($mock) use ($team): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->with('Hello team', (int) $team->id, null, null, false, null)
                ->andReturn([
                    'response' => 'Mocked assistant reply',
                    'routed_to' => 'Default router',
                    'usage' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                ]);
        });

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/chat', [
                'message' => 'Hello team',
                'prompt_key' => "  \t  ",
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('routed_to', 'Default router');
    }
}
