<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\AnonymousAgent;
use Tests\TestCase;

class ChatAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_returns_401_when_unauthenticated_and_no_recipient(): void
    {
        $response = $this->postJson(route('chat.assistant'), [
            'message' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_assistant_returns_validation_error_when_message_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), []);

        $response->assertStatus(422);
    }

    public function test_assistant_returns_response_with_context_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        AnonymousAgent::fake(['Test assistant response']);

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => 'Hello',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'response' => 'Test assistant response',
            'action_performed' => null,
        ]);
    }

    public function test_assistant_returns_stub_response_when_stub_mode_enabled(): void
    {
        Config::set('app.assistant_chat_stub', true);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => 'Test message',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action_performed' => null,
        ]);
        $response->assertSee('[Modo prueba]', false);
    }
}
