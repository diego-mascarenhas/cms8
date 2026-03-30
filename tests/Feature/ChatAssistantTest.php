<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Role;
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

    public function test_assistant_accepts_optional_flow_routing_key(): void
    {
        $user = User::factory()->create();
        AnonymousAgent::fake(['Test assistant response']);

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => 'Hello',
            'flow_routing_key' => 'contacts:nonexistent_section',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'response' => 'Test assistant response',
        ]);
    }

    public function test_assistant_accepts_preview_only_for_modal_draft(): void
    {
        $user = User::factory()->create();
        AnonymousAgent::fake(['Draft response']);

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => 'Hello',
            'preview_only' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'response' => 'Draft response',
        ]);
    }

    public function test_preview_only_strips_meta_wrappers_and_step_instructions(): void
    {
        $user = User::factory()->create();
        AnonymousAgent::fake([<<<'TEXT'
Aquí está el **primer mensaje** para enviar al cliente **Test** al número **722372858**:

Hola, este es el mensaje real para enviar.

---

Enviá ese mensaje y cuando el cliente responda, continuamos con el **Paso 2**.
Copiá ese texto y enviáselo por WhatsApp.
TEXT
        ]);

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => 'Hello',
            'preview_only' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'response' => 'Hola, este es el mensaje real para enviar.',
        ]);
    }

    public function test_admin_assistant_command_toggles_team_auto_respond(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('assistant_auto_respond', '1');

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => '/asistente off',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action_performed' => 'assistant_auto_respond_toggle',
            'assistant_auto_respond' => false,
        ]);
        $team->refresh();
        $this->assertSame('0', (string) $team->getSetting('assistant_auto_respond', '1'));

        $responseOn = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => '/assistant on',
        ]);
        $responseOn->assertStatus(200);
        $responseOn->assertJson(['assistant_auto_respond' => true]);
        $team->refresh();
        $this->assertSame('1', (string) $team->getSetting('assistant_auto_respond', '0'));
    }

    public function test_non_admin_cannot_use_assistant_auto_respond_command(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => '/asistente off',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
        $team->refresh();
        $this->assertSame('1', (string) $team->getSetting('assistant_auto_respond', '1'));
    }
}
