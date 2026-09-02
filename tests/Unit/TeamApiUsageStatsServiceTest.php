<?php

namespace Tests\Unit;

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\Module;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\TeamApiUsageStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_does_not_double_count_chat_reply_logs_when_conversation_rows_exist(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'ChatAssistantReplyService',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 140,
            'toon_tokens' => 100,
            'savings_percentage' => 29,
            'used_toon' => true,
        ]);

        $conversationId = (string) Str::uuid();
        AgentConversation::create([
            'id' => $conversationId,
            'user_id' => $user->id,
            'team_id' => $team->id,
            'title' => 'WhatsApp',
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
            'usage' => ['total_tokens' => 100],
            'meta' => [],
        ]);

        $stats = TeamApiUsageStatsService::forTeam((int) $team->id);

        $this->assertSame(1, $stats['totalCalls']);
        $this->assertSame(100, $stats['totalTokensUsed']);
        $this->assertSame(40, $stats['totalTokensSaved']);
        $this->assertSame(140, $stats['totalTokensWithoutToon']);
        $this->assertSame(28.57, $stats['averageSavings']);
    }

    public function test_by_module_shows_ocr_module_name_for_document_ai_ocr_logs(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $ocrModule = Module::query()->firstOrCreate(
            ['key' => 'ocr'],
            ['name' => 'OCR', 'is_core' => false, 'order' => 8, 'status' => 1],
        );

        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $ocrModule->id,
            'service' => 'DocumentAiOcrService',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 120,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $stats = TeamApiUsageStatsService::forTeam((int) $team->id);

        $this->assertSame(1, count($stats['byModule']));
        $row = reset($stats['byModule']);
        $this->assertSame('OCR', $row['module_name']);
        $this->assertSame(120, $row['tokens_used']);
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

    public function test_for_team_can_limit_usage_to_a_period(): void
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
            'json_tokens' => 80,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $previous = TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'PromptController',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 500,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);
        $previous->forceFill(['created_at' => now()->subMonth()])->save();

        $stats = TeamApiUsageStatsService::forTeam(
            (int) $team->id,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $this->assertSame(1, $stats['totalCalls']);
        $this->assertSame(80, $stats['totalTokensUsed']);
    }

    public function test_cost_summary_uses_catalog_market_rate_on_double_tokens(): void
    {
        config([
            'services.openrouter.cache_store' => 'array',
            'humano_pricing.token_billing.currency' => 'EUR',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response(['data' => []], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();

        TokenUsageLog::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => null,
            'service' => 'ContactSentimentAnalysisService',
            'json_size' => 10,
            'toon_size' => 0,
            'json_tokens' => 1_000_000,
            'toon_tokens' => 0,
            'savings_percentage' => 0,
            'used_toon' => false,
        ]);

        $summary = TeamApiUsageStatsService::costSummary((int) $team->id);

        $this->assertSame(8_000_000, $summary['tokens']);
        $this->assertSame(800, $summary['amount_cents']);
        $this->assertSame('EUR', $summary['currency']);
        $this->assertSame('8.000.000 / 8,00 EUR', $summary['formatted']);
    }
}
