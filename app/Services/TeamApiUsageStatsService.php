<?php

namespace App\Services;

use App\Models\AgentConversationMessage;
use App\Models\Module;
use App\Models\TokenUsageLog;
use App\Services\Billing\BilledUsage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class TeamApiUsageStatsService
{
    /**
     * Chat replies are billed from {@see AgentConversationMessage} when those
     * rows exist. Matching {@see TokenUsageLog} services are only a fallback
     * so the same turn is never counted twice. Their TOON savings still apply.
     *
     * @var list<string>
     */
    private const CHAT_REPLY_LOG_SERVICES = [
        'AssistantChatService',
        'ChatAssistantReplyService',
    ];

    /**
     * Usage that has no team module still needs its own line on the invoice.
     *
     * @var array<string, string>
     */
    private const NAMED_SERVICE_LABELS = [
        'MeetingSummaryService' => 'Reuniones',
        'FinanceCfoBriefService' => 'Análisis CFO',
    ];

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
     *     totalBilledTokens: int,
     *     totalBilledTokensSaved: int,
     *     byModule: array<string, array{module_name: string, count: int, tokens_used: int, tokens_saved: int, tokens_billed: int, tokens_saved_billed: int}>
     * }
     */
    public static function forTeam(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $conversationStats = self::aggregateAssistantConversationUsage($teamId, $from, $to);
        $chatReplyLogs = self::aggregateChatReplyLogs($teamId, $from, $to);
        $useConversation = $conversationStats['tokens_used'] > 0 || $conversationStats['calls'] > 0;
        $chatUsed = $useConversation ? $conversationStats['tokens_used'] : $chatReplyLogs['tokens_used'];
        $chatBilled = $useConversation ? $conversationStats['tokens_billed'] : $chatReplyLogs['tokens_billed'];
        $chatCalls = $useConversation ? $conversationStats['calls'] : $chatReplyLogs['calls'];
        $chatSaved = $chatReplyLogs['tokens_saved'];
        $chatSavedBilled = $chatReplyLogs['tokens_saved_billed'];
        $nonChatBilled = self::billedTotalsFromLogs(self::nonChatLogQuery($teamId, $from, $to));

        $totalCalls = self::nonChatLogQuery($teamId, $from, $to)->count() + $chatCalls;

        $nonChatSaved = (int) (self::nonChatLogQuery($teamId, $from, $to)
            ->where('used_toon', true)
            ->selectRaw('COALESCE(SUM(json_tokens - toon_tokens), 0) as aggregate')
            ->value('aggregate') ?? 0);
        $totalTokensSaved = $nonChatSaved + $chatSaved;

        $totalTokensUsed = self::sumTokensUsedFromNonChatLogs($teamId, $from, $to) + $chatUsed;

        $totalTokensWithoutToon = (int) self::nonChatLogQuery($teamId, $from, $to)->sum('json_tokens')
            + $chatUsed
            + $chatSaved;

        $averageSavings = $totalTokensWithoutToon > 0
            ? round(min(100, ($totalTokensSaved / $totalTokensWithoutToon) * 100), 2)
            : 0.0;

        $byModule = self::callsByModuleFromNonChatLogs($teamId, $from, $to);

        if ($chatUsed > 0 || $chatCalls > 0 || $chatSaved > 0)
        {
            self::mergeAssistantChatConversationUsageIntoByModule(
                $byModule,
                [
                    'calls' => $chatCalls,
                    'tokens_used' => $chatUsed,
                    'tokens_saved' => $chatSaved,
                    'tokens_billed' => $chatBilled,
                    'tokens_saved_billed' => $chatSavedBilled,
                ],
            );
        }

        $byModule = self::mergeByModuleName($byModule);

        return [
            'totalCalls' => $totalCalls,
            'totalTokensSaved' => $totalTokensSaved,
            'averageSavings' => $averageSavings,
            'totalTokensUsed' => $totalTokensUsed,
            'totalTokensWithoutToon' => $totalTokensWithoutToon,
            'totalBilledTokens' => $nonChatBilled['tokens'] + $chatBilled,
            'totalBilledTokensSaved' => $nonChatBilled['saved'] + $chatSavedBilled,
            'byModule' => $byModule,
        ];
    }

    /**
     * @return array{tokens: int, amount_cents: int, currency: string, formatted: string}
     */
    public static function costSummary(int $teamId): array
    {
        $tokens = (int) self::forTeam($teamId)['totalBilledTokens'];
        $presenter = app(\App\Services\Billing\ClientTokenPresenter::class)
            ->usingTeam(\App\Models\Team::query()->find($teamId));
        $presented = [
            'total_tokens' => $tokens,
            'amount_cents' => $presenter->costCents($tokens, 0, null),
        ];
        $currency = TokenBillingRateService::displayCurrency();
        $amountCents = $presented['amount_cents'];
        $amount = $amountCents / 100;
        $tokenLabel = number_format($presented['total_tokens'], 0, ',', '.');
        $priceLabel = number_format($amount, 2, ',', '.').' '.$currency;

        return [
            'tokens' => $presented['total_tokens'],
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'formatted' => $tokenLabel.' / '.$priceLabel,
        ];
    }

    /**
     * @return Builder<\App\Models\TokenUsageLog>
     */
    private static function nonChatLogQuery(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Builder
    {
        $query = TokenUsageLog::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotIn('service', self::CHAT_REPLY_LOG_SERVICES);

        return self::constrainPeriod($query, $from, $to);
    }

    /**
     * @return Builder<\App\Models\TokenUsageLog>
     */
    private static function chatReplyLogQuery(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Builder
    {
        $query = TokenUsageLog::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereIn('service', self::CHAT_REPLY_LOG_SERVICES);

        return self::constrainPeriod($query, $from, $to);
    }

    /**
     * @return array{calls: int, tokens_used: int, tokens_saved: int, tokens_billed: int, tokens_saved_billed: int}
     */
    private static function aggregateChatReplyLogs(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $logs = self::chatReplyLogQuery($teamId, $from, $to)->get([
            'team_id',
            'json_tokens',
            'toon_tokens',
            'used_toon',
            'billed_usage',
            'created_at',
        ]);

        $tokensUsed = 0;
        $tokensSaved = 0;
        $tokensBilled = 0;
        $tokensSavedBilled = 0;

        foreach ($logs as $log)
        {
            $tokensUsed += $log->used_toon ? (int) $log->toon_tokens : (int) $log->json_tokens;
            if ($log->used_toon)
            {
                $tokensSaved += max(0, (int) $log->json_tokens - (int) $log->toon_tokens);
            }
            $billed = self::billedUsageForLog($log);
            $tokensBilled += BilledUsage::used($billed);
            $tokensSavedBilled += $billed['tokens_saved'];
        }

        return [
            'calls' => $logs->count(),
            'tokens_used' => $tokensUsed,
            'tokens_saved' => $tokensSaved,
            'tokens_billed' => $tokensBilled,
            'tokens_saved_billed' => $tokensSavedBilled,
        ];
    }

    /**
     * @param  Builder<\App\Models\TokenUsageLog>  $query
     * @return array{tokens: int, saved: int}
     */
    private static function billedTotalsFromLogs(Builder $query): array
    {
        $tokens = 0;
        $saved = 0;

        foreach ((clone $query)->get(['team_id', 'json_tokens', 'toon_tokens', 'used_toon', 'billed_usage', 'created_at']) as $log)
        {
            $billed = self::billedUsageForLog($log);
            $tokens += BilledUsage::used($billed);
            $saved += $billed['tokens_saved'];
        }

        return [
            'tokens' => $tokens,
            'saved' => $saved,
        ];
    }

    /**
     * @return array{model: string|null, prompt_tokens: int, completion_tokens: int, tokens_saved: int}
     */
    private static function billedUsageForLog(TokenUsageLog $log): array
    {
        $stored = BilledUsage::read(is_array($log->billed_usage) ? $log->billed_usage : null);
        if ($stored !== null)
        {
            return $stored;
        }

        $used = $log->used_toon ? (int) $log->toon_tokens : (int) $log->json_tokens;
        if ($used <= 0)
        {
            $used = (int) $log->json_tokens;
        }
        $saved = $log->used_toon ? max(0, (int) $log->json_tokens - (int) $log->toon_tokens) : 0;

        return BilledUsage::capture((int) $log->team_id, $used, 0, $saved, null, $log->created_at);
    }

    /**
     * @param  Builder<\App\Models\TokenUsageLog|\App\Models\AgentConversationMessage>  $query
     * @return Builder<\App\Models\TokenUsageLog|\App\Models\AgentConversationMessage>
     */
    private static function constrainPeriod(Builder $query, ?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        if ($from !== null)
        {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null)
        {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    private static function sumTokensUsedFromNonChatLogs(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        $toonSum = (int) self::nonChatLogQuery($teamId, $from, $to)->where('used_toon', true)->sum('toon_tokens');
        $jsonSum = (int) self::nonChatLogQuery($teamId, $from, $to)->where('used_toon', false)->sum('json_tokens');

        return $toonSum + $jsonSum;
    }

    /**
     * @return array{calls: int, tokens_used: int, tokens_billed: int}
     */
    private static function aggregateAssistantConversationUsage(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $query = AgentConversationMessage::query()
            ->where('role', 'assistant')
            ->whereHas('conversation', function ($query) use ($teamId)
            {
                $query->where('team_id', $teamId);
            });

        $messages = self::constrainPeriod($query, $from, $to)->get(['usage', 'billed_usage', 'created_at']);

        $calls = 0;
        $tokensUsed = 0;
        $tokensBilled = 0;

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
            $billed = BilledUsage::read(is_array($message->billed_usage) ? $message->billed_usage : null);
            if ($billed === null)
            {
                if ($prompt + $completion <= 0)
                {
                    $prompt = $total;
                    $completion = 0;
                }
                $billed = BilledUsage::capture(
                    $teamId,
                    $prompt,
                    $completion,
                    max(0, (int) ($usage['tokens_saved'] ?? 0)),
                    is_string($usage['model'] ?? null) ? $usage['model'] : null,
                    $message->created_at,
                );
            }
            $tokensBilled += BilledUsage::used($billed);
        }

        return [
            'calls' => $calls,
            'tokens_used' => $tokensUsed,
            'tokens_billed' => $tokensBilled,
        ];
    }

    /**
     * @return array<string, array{module_name: string, count: int, tokens_used: int, tokens_saved: int}>
     */
    private static function callsByModuleFromNonChatLogs(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $rows = self::nonChatLogQuery($teamId, $from, $to)
            ->whereNotNull('module_id')
            ->with('module:id,name')
            ->get();

        $byModule = $rows
            ->groupBy('module_id')
            ->map(function ($logs)
            {
                $first = $logs->first();

                return [
                    'module_name' => $first->module->name ?? 'Unknown',
                    'count' => $logs->count(),
                    'tokens_used' => self::tokensUsedByLogs($logs),
                    'tokens_saved' => self::tokensSavedByLogs($logs),
                    'tokens_billed' => self::tokensBilledByLogs($logs),
                    'tokens_saved_billed' => self::tokensSavedBilledByLogs($logs),
                ];
            })
            ->toArray();

        $serviceLogs = self::nonChatLogQuery($teamId, $from, $to)
            ->whereNull('module_id')
            ->whereIn('service', array_keys(self::NAMED_SERVICE_LABELS))
            ->get()
            ->groupBy('service');

        foreach ($serviceLogs as $service => $logs)
        {
            $byModule['service:'.$service] = [
                'module_name' => self::NAMED_SERVICE_LABELS[$service],
                'count' => $logs->count(),
                'tokens_used' => self::tokensUsedByLogs($logs),
                'tokens_saved' => self::tokensSavedByLogs($logs),
                'tokens_billed' => self::tokensBilledByLogs($logs),
                'tokens_saved_billed' => self::tokensSavedBilledByLogs($logs),
            ];
        }

        return $byModule;
    }

    /**
     * @param  Collection<int, TokenUsageLog>  $logs
     */
    private static function tokensUsedByLogs(Collection $logs): int
    {
        return (int) $logs->sum(function ($log)
        {
            return $log->used_toon ? (int) $log->toon_tokens : (int) $log->json_tokens;
        });
    }

    /**
     * @param  Collection<int, TokenUsageLog>  $logs
     */
    private static function tokensSavedByLogs(Collection $logs): int
    {
        return (int) $logs->where('used_toon', true)->sum(function ($log)
        {
            return (int) $log->json_tokens - (int) $log->toon_tokens;
        });
    }

    /**
     * @param  Collection<int, TokenUsageLog>  $logs
     */
    private static function tokensBilledByLogs(Collection $logs): int
    {
        return (int) $logs->sum(fn (TokenUsageLog $log): int => BilledUsage::used(self::billedUsageForLog($log)));
    }

    /**
     * @param  Collection<int, TokenUsageLog>  $logs
     */
    private static function tokensSavedBilledByLogs(Collection $logs): int
    {
        return (int) $logs->sum(fn (TokenUsageLog $log): int => self::billedUsageForLog($log)['tokens_saved']);
    }

    /**
     * Prefer merging assistant conversation totals into TokenUsage slices for the team's {@see Module}
     * with {@code key} {@code chat} so the donut chart legend does not show the label twice.
     *
     * @param  array<int|string, array{module_name: string, count: int, tokens_used: int, tokens_saved: int}>  $byModule  Mutated.
     * @param  array{calls: int, tokens_used: int, tokens_saved?: int, tokens_billed?: int, tokens_saved_billed?: int}  $conversationStats
     */
    private static function mergeAssistantChatConversationUsageIntoByModule(array &$byModule, array $conversationStats): void
    {
        $chatLabel = Module::query()->where('key', 'chat')->value('name') ?? 'Chat';
        $chatModuleId = Module::query()->where('key', 'chat')->value('id');

        $incoming = [
            'module_name' => $chatLabel,
            'count' => $conversationStats['calls'],
            'tokens_used' => $conversationStats['tokens_used'],
            'tokens_saved' => (int) ($conversationStats['tokens_saved'] ?? 0),
            'tokens_billed' => (int) ($conversationStats['tokens_billed'] ?? 0),
            'tokens_saved_billed' => (int) ($conversationStats['tokens_saved_billed'] ?? 0),
        ];

        if ($chatModuleId !== null)
        {
            foreach ($byModule as $key => $row)
            {
                if ((string) $key === (string) $chatModuleId)
                {
                    $byModule[$key]['count'] += $incoming['count'];
                    $byModule[$key]['tokens_used'] += $incoming['tokens_used'];
                    $byModule[$key]['tokens_saved'] = ($byModule[$key]['tokens_saved'] ?? 0) + $incoming['tokens_saved'];
                    $byModule[$key]['tokens_billed'] = ($byModule[$key]['tokens_billed'] ?? 0) + $incoming['tokens_billed'];
                    $byModule[$key]['tokens_saved_billed'] = ($byModule[$key]['tokens_saved_billed'] ?? 0) + $incoming['tokens_saved_billed'];

                    return;
                }
            }
        }

        $byModule['chat_conversations'] = $incoming;
    }

    /**
     * Collapses duplicate {@code module_name} buckets (same label, distinct keys).
     *
     * @param  array<int|string, array{module_name: string, count: int, tokens_used: int, tokens_saved: int}>  $byModule
     * @return array<string, array{module_name: string, count: int, tokens_used: int, tokens_saved: int}>
     */
    private static function mergeByModuleName(array $byModule): array
    {
        $merged = [];

        foreach ($byModule as $entry)
        {
            $name = (string) ($entry['module_name'] ?? '');
            if ($name === '')
            {
                continue;
            }

            if (! isset($merged[$name]))
            {
                $merged[$name] = $entry;

                continue;
            }

            $merged[$name]['count'] = ($merged[$name]['count'] ?? 0) + ($entry['count'] ?? 0);
            $merged[$name]['tokens_used'] = ($merged[$name]['tokens_used'] ?? 0) + ($entry['tokens_used'] ?? 0);
            $merged[$name]['tokens_saved'] = ($merged[$name]['tokens_saved'] ?? 0) + ($entry['tokens_saved'] ?? 0);
            $merged[$name]['tokens_billed'] = ($merged[$name]['tokens_billed'] ?? 0) + ($entry['tokens_billed'] ?? 0);
            $merged[$name]['tokens_saved_billed'] = ($merged[$name]['tokens_saved_billed'] ?? 0) + ($entry['tokens_saved_billed'] ?? 0);
        }

        return $merged;
    }
}
