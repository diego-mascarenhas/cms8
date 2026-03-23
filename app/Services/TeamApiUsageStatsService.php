<?php

namespace App\Services;

use App\Models\AgentConversationMessage;
use App\Models\Module;
use App\Models\TokenUsageLog;

final class TeamApiUsageStatsService
{
    /**
     * Dashboard widget stats: combines {@see TokenUsageLog} (non-chat API calls) with
     * assistant {@see AgentConversationMessage} rows for the team (chat usage is stored on messages).
     *
     * @return array{
     *     totalCalls: int,
     *     totalTokensSaved: int,
     *     averageSavings: float,
     *     totalTokensUsed: int,
     *     totalTokensWithoutToon: int,
     *     byModule: array<string, array{module_name: string, count: int, tokens_used: int, tokens_saved: int}>
     * }
     */
    public static function forTeam(int $teamId): array
    {
        $conversationStats = self::aggregateAssistantConversationUsage($teamId);

        $totalCalls = self::nonChatLogQuery($teamId)->count() + $conversationStats['calls'];

        $totalTokensSaved = (int) (self::nonChatLogQuery($teamId)
            ->where('used_toon', true)
            ->selectRaw('COALESCE(SUM(json_tokens - toon_tokens), 0) as aggregate')
            ->value('aggregate') ?? 0);

        $totalTokensUsed = self::sumTokensUsedFromNonChatLogs($teamId) + $conversationStats['tokens_used'];

        $totalTokensWithoutToon = (int) self::nonChatLogQuery($teamId)->sum('json_tokens')
            + $conversationStats['tokens_used'];

        $averageSavings = $totalTokensWithoutToon > 0
            ? round(min(100, ($totalTokensSaved / $totalTokensWithoutToon) * 100), 2)
            : 0.0;

        $byModule = self::callsByModuleFromNonChatLogs($teamId);
        if ($conversationStats['tokens_used'] > 0 || $conversationStats['calls'] > 0)
        {
            $chatLabel = Module::query()->where('key', 'chat')->value('name') ?? 'Chat';
            $byModule['chat_conversations'] = [
                'module_name' => $chatLabel,
                'count' => $conversationStats['calls'],
                'tokens_used' => $conversationStats['tokens_used'],
                'tokens_saved' => 0,
            ];
        }

        return [
            'totalCalls' => $totalCalls,
            'totalTokensSaved' => $totalTokensSaved,
            'averageSavings' => $averageSavings,
            'totalTokensUsed' => $totalTokensUsed,
            'totalTokensWithoutToon' => $totalTokensWithoutToon,
            'byModule' => $byModule,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\TokenUsageLog>
     */
    private static function nonChatLogQuery(int $teamId)
    {
        return TokenUsageLog::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('service', '!=', 'AssistantChatService');
    }

    private static function sumTokensUsedFromNonChatLogs(int $teamId): int
    {
        $toonSum = (int) self::nonChatLogQuery($teamId)->where('used_toon', true)->sum('toon_tokens');
        $jsonSum = (int) self::nonChatLogQuery($teamId)->where('used_toon', false)->sum('json_tokens');

        return $toonSum + $jsonSum;
    }

    /**
     * @return array{calls: int, tokens_used: int}
     */
    private static function aggregateAssistantConversationUsage(int $teamId): array
    {
        $messages = AgentConversationMessage::query()
            ->where('role', 'assistant')
            ->whereHas('conversation', function ($query) use ($teamId)
            {
                $query->where('team_id', $teamId);
            })
            ->get(['usage']);

        $calls = 0;
        $tokensUsed = 0;

        foreach ($messages as $message)
        {
            $usage = $message->usage ?? [];
            if (! is_array($usage) || $usage === [])
            {
                continue;
            }

            $prompt = (int) ($usage['prompt_tokens'] ?? 0);
            $completion = (int) ($usage['completion_tokens'] ?? 0);
            $total = (int) ($usage['total_tokens'] ?? ($prompt + $completion));

            if ($total <= 0)
            {
                continue;
            }

            $calls++;
            $tokensUsed += $total;
        }

        return [
            'calls' => $calls,
            'tokens_used' => $tokensUsed,
        ];
    }

    /**
     * @return array<string, array{module_name: string, count: int, tokens_used: int, tokens_saved: int}>
     */
    private static function callsByModuleFromNonChatLogs(int $teamId): array
    {
        $rows = self::nonChatLogQuery($teamId)
            ->whereNotNull('module_id')
            ->with('module:id,name')
            ->get();

        return $rows
            ->groupBy('module_id')
            ->map(function ($logs)
            {
                $first = $logs->first();

                return [
                    'module_name' => $first->module->name ?? 'Unknown',
                    'count' => $logs->count(),
                    'tokens_used' => (int) $logs->sum(function ($log)
                    {
                        return $log->used_toon ? (int) $log->toon_tokens : (int) $log->json_tokens;
                    }),
                    'tokens_saved' => (int) $logs->where('used_toon', true)->sum(function ($log)
                    {
                        return (int) $log->json_tokens - (int) $log->toon_tokens;
                    }),
                ];
            })
            ->toArray();
    }
}
