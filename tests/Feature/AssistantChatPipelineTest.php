<?php

namespace Tests\Feature;

use App\Livewire\AssistantChat;
use App\Models\User;
use App\Services\ChatAssistantReplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantChatPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_text_message_uses_chat_assistant_reply_service(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        $this->mock(ChatAssistantReplyService::class, function ($mock): void
        {
            $mock->shouldReceive('getReply')
                ->once()
                ->andReturn([
                    'success' => true,
                    'text' => 'Mensaje simulado del asistente con herramientas.',
                    'routed_to' => 'General',
                    'usage' => [
                        'prompt_tokens' => 1,
                        'completion_tokens' => 2,
                        'total_tokens' => 3,
                    ],
                    'meta' => [],
                    'tool_calls' => [],
                    'tool_results' => [],
                    'assistant_flow_routing_key_specified' => false,
                    'assistant_flow_routing_key' => null,
                ]);
        });

        Livewire::actingAs($user->fresh())
            ->test(AssistantChat::class)
            ->set('input', 'Hola')
            ->call('sendMessage')
            ->assertSet('input', '')
            ->assertSee('Mensaje simulado del asistente con herramientas.', false);
    }

    public function test_assistant_chat_view_scales_markdown_headings_in_bubbles(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        Livewire::actingAs($user->fresh())
            ->test(AssistantChat::class, ['hideHeader' => true])
            ->assertSee('.assistant-content h1', false)
            ->assertSee('font-size: 1.05rem', false)
            ->assertSee('.assistant-content h2', false);
    }
}
