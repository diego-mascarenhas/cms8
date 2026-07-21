<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamAssistantAutomationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_chat_with_automation_slug_uses_entry_prompt(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'slug' => 'soporte-web',
            'entry_prompt_key' => 'contacts:landing',
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        $this->mock(AssistantChatService::class, function ($mock) use ($team): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->with('Hello', $team->id, null, null, false, 'contacts:landing')
                ->andReturn([
                    'response' => 'From automation',
                    'routed_to' => 'contacts:landing',
                ]);
        });

        $response = $this->postJson('/api/team/assistant/chat', [
            'message' => 'Hello',
            'automation_slug' => 'soporte-web',
        ], [
            'Authorization' => 'Bearer '.$tokenValue,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'response' => 'From automation',
            'routed_to' => 'contacts:landing',
            'automation_id' => $automation->id,
            'automation_slug' => 'soporte-web',
        ]);
    }

    public function test_assistant_chat_returns_404_for_unknown_automation_slug(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $response = $this->postJson('/api/team/assistant/chat', [
            'message' => 'Hello',
            'automation_slug' => 'missing',
        ], [
            'Authorization' => 'Bearer '.$tokenValue,
        ]);

        $response->assertStatus(404);
    }

    public function test_assistant_chat_returns_403_when_api_channel_disabled(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        Automation::factory()->create([
            'team_id' => $team->id,
            'slug' => 'no-api',
            'channels' => Automation::normalizeChannels(['whatsapp' => true]),
        ]);

        $response = $this->postJson('/api/team/assistant/chat', [
            'message' => 'Hello',
            'automation_slug' => 'no-api',
        ], [
            'Authorization' => 'Bearer '.$tokenValue,
        ]);

        $response->assertStatus(403);
    }
}
