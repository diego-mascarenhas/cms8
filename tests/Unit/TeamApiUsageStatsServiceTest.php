<?php

namespace Tests\Unit;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Module;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\TeamApiUsageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamApiUsageStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_combines_non_chat_logs_and_conversation_usage_for_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();

        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'PromptController',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 50,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $conversationId = (string) Str::uuid();
        AgentConversation::create([
            'id' => $conversationId,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'title' => 'Test conversation',
        ]);

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => 'chat_assistant',
            'role' => 'assistant',
            'content' => 'Hello',
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 40,
                'total_tokens' => 50,
            ],
            'meta' => [],
        ]);

        $stats = TeamApiUsageStatsService::forTeam((int) $team->id);

        $this->assertSame(2, $stats['totalCalls']);
        $this->assertSame(100, $stats['totalTokensUsed']);
        $this->assertSame(100, $stats['totalTokensWithoutToon']);
        $this->assertSame(1, count($stats['byModule']));
        $chatSlice = reset($stats['byModule']);
        $this->assertIsArray($chatSlice);
        $this->assertSame(50, $chatSlice['tokens_used']);
        $this->assertSame(1, $chatSlice['count']);
        $this->assertArrayNotHasKey('chat_conversations', $stats['byModule']);
    }

    public function test_by_module_merges_chat_api_logs_and_agent_conversations_under_one_label(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $chatModule = Module::query()->firstOrCreate(
            ['key' => 'chat'],
            ['name' => 'Chat', 'is_core' => false, 'order' => 0, 'status' => 1],
        );

        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $chatModule->id,
            'service' => 'PromptController',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 30,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $conversationId = (string) Str::uuid();
        AgentConversation::create([
            'id' => $conversationId,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'title' => 'Test conversation',
        ]);

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => 'chat_assistant',
            'role' => 'assistant',
            'content' => 'Hello',
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 40,
                'total_tokens' => 50,
            ],
            'meta' => [],
        ]);

        $stats = TeamApiUsageStatsService::forTeam((int) $team->id);

        $this->assertSame(1, count($stats['byModule']));
        $row = reset($stats['byModule']);
        $this->assertSame('Chat', $row['module_name']);
        $this->assertSame(80, $row['tokens_used']);
        $this->assertSame(2, $row['count']);
    }

    public function test_excludes_assistant_chat_service_logs_in_favor_of_conversation_rows(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'AssistantChatService',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 999,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $conversationId = (string) Str::uuid();
        AgentConversation::create([
            'id' => $conversationId,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'title' => 'Chat',
        ]);

        AgentConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'agent' => 'chat_assistant',
            'role' => 'assistant',
            'content' => 'Reply',
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => ['total_tokens' => 50],
            'meta' => [],
        ]);

        $stats = TeamApiUsageStatsService::forTeam((int) $team->id);

        $this->assertSame(1, $stats['totalCalls']);
        $this->assertSame(50, $stats['totalTokensUsed']);
        $this->assertSame(50, $stats['totalTokensWithoutToon']);
    }

    public function test_does_not_include_other_teams_data(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $teamA = $userA->ownedTeams()->first();
        $userA->forceFill(['current_team_id' => $teamA->id])->save();

        $userB = User::factory()->withPersonalTeam()->create();
        $teamB = $userB->ownedTeams()->first();

        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $teamB->id,
            'module_id' => null,
            'service' => 'PromptController',
            'json_size' => 1,
            'toon_size' => 0,
            'json_tokens' => 500,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $stats = TeamApiUsageStatsService::forTeam((int) $teamA->id);

        $this->assertSame(0, $stats['totalCalls']);
        $this->assertSame(0, $stats['totalTokensUsed']);
    }
}
