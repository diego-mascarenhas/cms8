<?php

namespace Tests\Feature;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AgentConversationContextService;
use App\Services\AssistantToolIntentPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssistantToolFlowStickyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Team, 1: string}
     */
    protected function createTeamWithCommercePrompt(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $module = Module::query()->create([
            'name' => 'Sticky commerce',
            'key' => 'sticky_commerce_'.substr(md5((string) $team->id), 0, 8),
            'is_core' => false,
            'status' => 1,
        ]);

        Prompt::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'chat_commerce',
            'section_label' => 'Shop flow',
            'prompt_instruction' => 'Commerce-only instructions.',
            'is_active' => true,
            'order' => 0,
        ]);

        return [$team, 'chat_commerce'];
    }

    public function test_sticky_routing_key_keeps_commerce_flow_without_keywords(): void
    {
        [$team, $routingKey] = $this->createTeamWithCommercePrompt();
        $service = app(AssistantToolIntentPromptService::class);

        $resolution = $service->resolveFlowForToolAssistant((int) $team->id, 'Hola, seguimos', $routingKey);

        $this->assertNotNull($resolution['prompt']);
        $this->assertSame('chat_commerce', $resolution['prompt']->section_key);
        $this->assertSame($routingKey, $resolution['routing_key']);
        $this->assertSame('omit', $resolution['persist_assistant_flow_key']);
    }

    public function test_flow_reset_phrase_clears_sticky_when_no_new_intent(): void
    {
        [$team, $routingKey] = $this->createTeamWithCommercePrompt();
        $service = app(AssistantToolIntentPromptService::class);

        $resolution = $service->resolveFlowForToolAssistant((int) $team->id, 'Cambiar de tema por favor', $routingKey);

        $this->assertNull($resolution['prompt']);
        $this->assertSame('clear', $resolution['persist_assistant_flow_key']);
    }

    public function test_invalid_sticky_key_triggers_clear_when_no_intent_match(): void
    {
        [$team] = $this->createTeamWithCommercePrompt();
        $service = app(AssistantToolIntentPromptService::class);

        $resolution = $service->resolveFlowForToolAssistant((int) $team->id, 'Solo hola', 'definitely_missing_key');

        $this->assertNull($resolution['prompt']);
        $this->assertSame('clear', $resolution['persist_assistant_flow_key']);
    }

    public function test_persist_messages_updates_assistant_flow_routing_key(): void
    {
        [$team] = $this->createTeamWithCommercePrompt();
        $user = User::factory()->create();

        $context = app(AgentConversationContextService::class);
        $context->persistMessages(
            $user->id,
            'Catálogo',
            'OK',
            null,
            [],
            [],
            [],
            [],
            (int) $team->id,
            true,
            'chat_commerce',
        );

        $conversation = AgentConversation::where('user_id', $user->id)
            ->where('team_id', $team->id)
            ->first();
        $this->assertNotNull($conversation);
        $this->assertSame('chat_commerce', $conversation->assistant_tool_flow_routing_key);

        $key = $context->getAssistantToolFlowRoutingKey($user->id, (int) $team->id);
        $this->assertSame('chat_commerce', $key);
    }

    public function test_get_assistant_tool_flow_routing_key_reads_from_conversation(): void
    {
        [$team] = $this->createTeamWithCommercePrompt();
        $user = User::factory()->create();

        $conversationId = (string) Str::uuid();
        $conversation = AgentConversation::create([
            'id' => $conversationId,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'title' => 'Chat',
            'assistant_tool_flow_routing_key' => 'chat_commerce',
        ]);

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'agent' => AgentConversationContextService::AGENT_NAME,
            'role' => 'user',
            'content' => 'Hi',
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ]);

        $context = app(AgentConversationContextService::class);
        $this->assertSame('chat_commerce', $context->getAssistantToolFlowRoutingKey($user->id, (int) $team->id));
    }
}
