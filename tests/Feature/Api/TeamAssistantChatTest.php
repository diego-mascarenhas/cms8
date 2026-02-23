<?php

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\User;
use App\Services\AssistantChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamAssistantChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_chat_returns_401_without_token(): void
    {
        $response = $this->postJson('/api/team/assistant/chat', [
            'message' => 'Hello',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Token not provided']);
    }

    public function test_assistant_chat_returns_401_with_invalid_token(): void
    {
        $response = $this->postJson('/api/team/assistant/chat', [
            'message' => 'Hello',
        ], [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid token']);
    }

    public function test_assistant_chat_returns_validation_error_when_message_missing(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $response = $this->postJson('/api/team/assistant/chat', [], [
            'Authorization' => 'Bearer '.$tokenValue,
        ]);

        $response->assertStatus(422);
    }

    public function test_assistant_chat_returns_response_with_valid_token(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $this->mock(AssistantChatService::class, function ($mock) use ($team): void {
            $mock->shouldReceive('run')
                ->once()
                ->with('Hello', $team->id, null, null, false, null)
                ->andReturn([
                    'response' => 'Hi there',
                    'routed_to' => 'general',
                ]);
        });

        $response = $this->postJson('/api/team/assistant/chat', [
            'message' => 'Hello',
        ], [
            'Authorization' => 'Bearer '.$tokenValue,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'response' => 'Hi there',
            'routed_to' => 'general',
        ]);
    }
}
