<?php

namespace Tests\Feature;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantActivityPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_team_assistant_activity_page(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->withPersonalTeam()->create();
        $team = $admin->currentTeam ?? $admin->ownedTeams()->first();
        $admin->forceFill(['current_team_id' => $team->id])->save();
        $admin->assignRole('admin');

        $conversation = AgentConversation::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'team_id' => $team->id,
            'title' => 'Billing follow-up',
        ]);

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'user_id' => $admin->id,
            'agent' => 'chat_assistant',
            'role' => 'assistant',
            'content' => 'Test response',
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [
                'prompt_tokens' => 1200,
                'completion_tokens' => 300,
                'total_tokens' => 1500,
            ],
            'meta' => [],
        ]);

        $response = $this->actingAs($admin)->get(route('assistant.activity'));

        $response->assertOk();
        $response->assertSee('Actividad de IA');

        $dataResponse = $this->actingAs($admin)->getJson(route('assistant.activity.data', [
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $dataResponse->assertOk();
        $dataResponse->assertJsonFragment([
            'conversation_title' => 'Billing follow-up',
            'total_tokens_value' => 1500,
        ]);
    }

    public function test_non_admin_cannot_view_team_assistant_activity_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($user)->get(route('assistant.activity'));

        $response->assertForbidden();
    }
}
