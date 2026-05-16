<?php

namespace Tests\Feature;

use App\Livewire\AssistantChat;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Team;
use App\Models\User;
use App\Services\AgentConversationContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantContextResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_archives_active_thread_and_clears_display_and_prompt_history(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->currentTeam->id;
        $service = app(AgentConversationContextService::class);

        $service->persistMessages($user->id, 'Hola', 'Respuesta uno', null, [], [], [], [], $teamId);
        $archivedConversation = AgentConversation::query()
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->whereNull('archived_at')
            ->first();

        $this->assertNotNull($archivedConversation);
        $this->assertCount(2, $archivedConversation->messages);

        $response = $this->actingAs($user)->postJson(route('chat.assistant-reset-context'));

        $response->assertOk();
        $response->assertJson(['success' => true, 'messages' => []]);

        $archivedConversation->refresh();
        $this->assertNotNull($archivedConversation->archived_at);

        $this->assertSame([], $service->getMessagesForDisplay($user->id, 50, $teamId));
        $this->assertSame([], $service->getHistoryForPrompt($user->id, 20, $teamId));

        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $archivedConversation->id,
            'role' => 'user',
            'content' => 'Hola',
        ]);

        $service->persistMessages($user->id, 'Nuevo inicio', 'Respuesta dos', null, [], [], [], [], $teamId);

        $activeConversation = $service->getAssistantConversationForUser($user->id, $teamId);
        $this->assertNotNull($activeConversation);
        $this->assertNotSame($archivedConversation->id, $activeConversation->id);
        $this->assertNull($activeConversation->archived_at);

        $history = $service->getHistoryForPrompt($user->id, 20, $teamId);
        $this->assertCount(2, $history);
        $this->assertSame('Nuevo inicio', $history[0]['body']);
    }

    public function test_assistant_history_endpoint_returns_only_active_thread(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->currentTeam->id;
        $service = app(AgentConversationContextService::class);

        $service->persistMessages($user->id, 'Antes', 'Ok', null, [], [], [], [], $teamId);
        $service->startFreshAssistantContext($user->id, $teamId);

        $response = $this->actingAs($user)->getJson(route('chat.assistant-history'));

        $response->assertOk();
        $response->assertJson(['messages' => []]);
    }

    public function test_reset_requires_authentication(): void
    {
        $response = $this->postJson(route('chat.assistant-reset-context'));

        $response->assertUnauthorized();
    }

    public function test_offcanvas_reset_event_clears_livewire_messages(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->currentTeam->id;
        $service = app(AgentConversationContextService::class);
        $service->persistMessages($user->id, 'Antes', 'Ok', null, [], [], [], [], $teamId);

        $this->actingAs($user);

        Livewire::test(AssistantChat::class, ['hideHeader' => true])
            ->assertCount('messages', 2)
            ->dispatch('assistant-reset-context')
            ->assertSet('messages', [])
            ->assertSet('conversationId', null);

        $this->assertSame([], $service->getMessagesForDisplay($user->id, 50, $teamId));
    }

    public function test_archived_conversation_messages_remain_in_database(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create();
        $teamId = (int) $team->id;

        $service = app(AgentConversationContextService::class);
        $service->persistMessages($user->id, 'Persistido', 'Guardado', null, [], [], [], [], $teamId);

        $messageCountBefore = AgentConversationMessage::query()->count();
        $service->startFreshAssistantContext($user->id, $teamId);

        $this->assertSame($messageCountBefore, AgentConversationMessage::query()->count());
        $this->assertGreaterThan(0, AgentConversation::query()->whereNotNull('archived_at')->count());
    }
}
