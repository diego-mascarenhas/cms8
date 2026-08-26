<?php

namespace Tests\Unit;

use App\Models\Module;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\TeamApiUsageStatsService;
use App\Services\TokenUsageLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenUsageLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_tokens_by_module_key_for_team_stats(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $module = Module::query()->firstOrCreate(
            ['key' => 'performance_insights'],
            ['name' => 'Team performance insights', 'is_core' => false, 'order' => 10, 'status' => 1],
        );

        TokenUsageLogService::log(
            teamId: (int) $team->id,
            service: 'UserDailyPerformanceInsightService',
            totalTokens: 120,
            moduleKey: 'performance_insights',
            inputSize: 500,
        );

        $this->assertDatabaseHas('token_usage_logs', [
            'team_id' => $team->id,
            'module_id' => $module->id,
            'service' => 'UserDailyPerformanceInsightService',
            'json_tokens' => 120,
        ]);

        $stats = TeamApiUsageStatsService::forTeam((int) $team->id);

        $this->assertSame(1, $stats['totalCalls']);
        $this->assertSame(120, $stats['totalTokensUsed']);
        $this->assertArrayHasKey($module->name, $stats['byModule']);
        $this->assertSame(120, $stats['byModule'][$module->name]['tokens_used']);
    }

    public function test_total_tokens_from_usage_object(): void
    {
        $usage = (object) [
            'promptTokens' => 30,
            'completionTokens' => 70,
        ];

        $this->assertSame(100, TokenUsageLogService::totalTokensFromUsage($usage));
    }

    public function test_chat_assistant_reply_logs_count_in_team_stats(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        $module = Module::query()->firstOrCreate(
            ['key' => 'chat'],
            ['name' => 'Chat', 'is_core' => true, 'order' => 1, 'status' => 1],
        );

        TokenUsageLogService::logFromAiResponse(
            (int) $team->id,
            'ChatAssistantReplyService',
            ['prompt_tokens' => 80, 'completion_tokens' => 40, 'total_tokens' => 120],
            moduleKey: 'chat',
        );

        $stats = TeamApiUsageStatsService::forTeam((int) $team->id);

        $this->assertSame(1, $stats['totalCalls']);
        $this->assertSame(120, $stats['totalTokensUsed']);
        $this->assertSame(120, $stats['byModule'][$module->name]['tokens_used']);
    }

    public function test_logs_toon_savings_from_tool_compression(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        TokenUsageLogService::logFromAiResponse(
            (int) $team->id,
            'ChatAssistantReplyService',
            ['prompt_tokens' => 80, 'completion_tokens' => 20, 'total_tokens' => 100],
            moduleKey: 'chat',
            toon: [
                'used_toon' => true,
                'json_size' => 800,
                'toon_size' => 400,
                'json_tokens' => 200,
                'toon_tokens' => 100,
                'tokens_saved' => 40,
                'savings_percentage' => 20.0,
            ],
        );

        $log = TokenUsageLog::withoutGlobalScopes()->where('team_id', $team->id)->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->used_toon);
        $this->assertSame(140, (int) $log->json_tokens);
        $this->assertSame(100, (int) $log->toon_tokens);

        $stats = TeamApiUsageStatsService::forTeam((int) $team->id);
        $this->assertSame(100, $stats['totalTokensUsed']);
        $this->assertSame(40, $stats['totalTokensSaved']);
        $this->assertSame(140, $stats['totalTokensWithoutToon']);
        $this->assertSame(28.57, $stats['averageSavings']);
    }

    public function test_skips_zero_token_logs(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $this->assertNotNull($team);

        TokenUsageLogService::log(
            teamId: (int) $team->id,
            service: 'NoOp',
            totalTokens: 0,
        );

        $this->assertSame(0, TokenUsageLog::withoutGlobalScopes()->where('team_id', $team->id)->count());
    }

    public function test_usage_with_toon_savings_copies_compression_fields(): void
    {
        $usage = TokenUsageLogService::usageWithToonSavings(
            ['prompt_tokens' => 80, 'completion_tokens' => 20, 'total_tokens' => 100],
            [
                'used_toon' => true,
                'json_tokens' => 140,
                'toon_tokens' => 100,
                'tokens_saved' => 40,
            ],
        );

        $this->assertSame(40, $usage['tokens_saved']);
        $this->assertSame(140, $usage['json_tokens']);
        $this->assertSame(100, $usage['toon_tokens']);
        $this->assertSame(100, $usage['total_tokens']);
    }

    public function test_usage_with_toon_savings_leaves_plain_usage_untouched(): void
    {
        $original = ['prompt_tokens' => 10, 'total_tokens' => 12];

        $this->assertSame(
            $original,
            TokenUsageLogService::usageWithToonSavings($original, ['used_toon' => false, 'tokens_saved' => 0]),
        );
    }
}
