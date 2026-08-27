<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\AgentConversationContextService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersistCommittedFlowOnContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_committed_presupuesto_replaces_landing_pin(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => 1,
            'data' => (object) ['chat_assistant_prompt_key' => 'contacts:landing'],
        ]);

        app(AgentConversationContextService::class)->persistCommittedFlowOnContact(
            (int) $contact->id,
            [
                'assistant_flow_routing_key_specified' => true,
                'assistant_flow_routing_key' => 'chat:assistant_presupuesto',
            ],
        );

        $this->assertSame(
            'chat:assistant_presupuesto',
            $contact->fresh()->inboundChatAssistantPromptKey(),
        );
    }

    public function test_ignores_reply_when_flow_was_not_committed(): void
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => 1,
            'data' => (object) ['chat_assistant_prompt_key' => 'contacts:landing'],
        ]);

        app(AgentConversationContextService::class)->persistCommittedFlowOnContact(
            (int) $contact->id,
            [
                'assistant_flow_routing_key_specified' => false,
                'assistant_flow_routing_key' => 'chat:assistant_presupuesto',
            ],
        );

        $this->assertSame('contacts:landing', $contact->fresh()->inboundChatAssistantPromptKey());
    }
}
