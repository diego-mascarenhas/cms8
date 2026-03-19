<?php

namespace Tests\Feature;

use App\Models\AgentConversation;
use App\Models\Team;
use App\Models\User;
use App\Services\AgentConversationContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentConversationTitleLengthTest extends TestCase
{
    use RefreshDatabase;

    public function test_persist_messages_truncates_conversation_title_for_long_whatsapp_promo(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create();
        $teamId = (int) $team->id;
        $longBody = str_repeat('Hola, cómo están? Podcast y links. ', 80);
        $this->assertGreaterThan(500, strlen($longBody));

        $service = app(AgentConversationContextService::class);
        $service->persistMessages(
            $user->id,
            $longBody,
            'Gracias por escribirnos.',
            null,
            [],
            [],
            [],
            [],
            $teamId,
        );

        $conversation = AgentConversation::query()
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->first();

        $this->assertNotNull($conversation);
        $this->assertLessThanOrEqual(191, mb_strlen($conversation->title));
        $this->assertStringContainsString('Hola', $conversation->title);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
        ]);
    }
}
