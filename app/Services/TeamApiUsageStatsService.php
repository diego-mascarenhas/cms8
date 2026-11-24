<?php

namespace App\Services;

use App\Models\TokenUsageLog;
use App\Services\Billing\BilledUsage;
use App\Services\Billing\ClientTokenPresenter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class TeamApiUsageStatsService
{
    /**
     * Usage that has no team module still needs its own line on the invoice.
     *
     * @var array<string, string>
     */
    private const NAMED_SERVICE_LABELS = [
        'AssistantChatService' => 'Chat',
        'ChatAssistantReplyService' => 'Chat',
        'MeetingSummaryService' => 'Reuniones',
        'FinanceCfoBriefService' => 'Análisis CFO',
    ];

    /**
     * Invoice stats from {@see TokenUsageLog} only. The conversation keeps the
     * model reply; it is not a second ledger.
     *
     * @return array{
     *     totalCalls: int,
     *     totalTokensSaved: int,
     *     averageSavings: float,
     *     totalTokensUsed: int,
     *     totalTokensWithoutToon: int,
     *     totalBilledTokens: int,
     *     totalBilledTokensSaved: int,
     *     totalBilledAmountCents: int,
     *     totalCostAmountCents: int,
     *     byModule: array<string, array{module_name: string, count: int, tokens_used: int, tokens_saved: int, tokens_billed: int, tokens_saved_billed: int, amount_cents: int}>
     * }
     */
    public static function forTeam(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $logs = self::teamLogQuery($teamId, $from, $to);
        $priced = self::pricedTotalsFromLogs($logs);

        $totalTokensSaved = (int) ((clone $logs)
            ->where('used_toon', true)
            ->selectRaw('COALESCE(SUM(json_tokens - toon_tokens), 0) as aggregate')
            ->value('aggregate') ?? 0);

        $totalTokensUsed = self::sumTokensUsed($teamId, $from, $to);

        $totalTokensWithoutToon = (int) (clone $logs)->sum('json_tokens');

        $averageSavings = $totalTokensWithoutToon > 0
            ? round(min(100, ($totalTokensSaved / $totalTokensWithoutToon) * 100), 2)
            : 0.0;

        return [
            'totalCalls' => (clone $logs)->count(),
            'totalTokensSaved' => $totalTokensSaved,
            'averageSavings' => $averageSavings,
            'totalTokensUsed' => $totalTokensUsed,
            'totalTokensWithoutToon' => $totalTokensWithoutToon,
            'totalBilledTokens' => $priced['tokens'],
            'totalBilledTokensSaved' => $priced['saved'],
            'totalBilledAmountCents' => $priced['amount_cents'],
            'totalCostAmountCents' => $priced['cost_cents'],
            'byModule' => self::mergeByModuleName(self::callsByModule($teamId, $from, $to)),
        ];
    }

    /**
     * @return array{tokens: int, amount_cents: int, currency: string, formatted: string}
     */
    public static function costSummary(int $teamId): array
    {
        $stats = self::forTeam($teamId);
        $tokens = (int) $stats['totalBilledTokens'];
        $presented = [
            'total_tokens' => $tokens,
            'amount_cents' => (int) $stats['totalBilledAmountCents'],
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
    private static function teamLogQuery(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Builder
    {
        $query = TokenUsageLog::withoutGlobalScopes()
            ->where('team_id', $teamId);

        return self::constrainPeriod($query, $from, $to);
    }

    /**
     * @param  Builder<\App\Models\TokenUsageLog>  $query
     * @return array{tokens: int, saved: int, amount_cents: int, cost_cents: int}
     */
    private static function pricedTotalsFromLogs(Builder $query): array
    {
        $tokens = 0;
        $saved = 0;
        $amount = 0;
        $cost = 0;
        $presenter = app(ClientTokenPresenter::class);

        foreach ((clone $query)->get(['team_id', 'service', 'json_tokens', 'toon_tokens', 'used_toon', 'billed_usage', 'created_at']) as $log)
        {
            $billed = self::billedUsageForLog($log);
            $tokens += BilledUsage::used($billed);
            $saved += $billed['tokens_saved'];
            $model = self::modelForLog($log, $billed);
            $amount += $presenter->costCents($billed['prompt_tokens'], $billed['completion_tokens'], $model, $log->created_at);
            $raw = $log->used_toon ? (int) $log->toon_tokens : (int) $log->json_tokens;
            if ($raw <= 0)
            {
                $raw = (int) $log->json_tokens;
            }
            $cost += $presenter->costCents($raw, 0, $model, $log->created_at);
        }

        return [
            'tokens' => $tokens,
            'saved' => $saved,
            'amount_cents' => $amount,
            'cost_cents' => $cost,
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

        return BilledUsage::capture((int) $log->team_id, $used, 0, $saved, self::modelForLog($log, []), $log->created_at);
    }

    /**
     * @param  array{model: string|null, prompt_tokens: int, completion_tokens: int, tokens_saved: int}  $billed
     */
    private static function modelForLog(TokenUsageLog $log, array $billed): ?string
    {
        $model = trim((string) ($billed['model'] ?? ''));
        if ($model !== '')
        {
            return $model;
        }

        if ($log->service === 'DocumentAiOcrService')
        {
            return app(DocumentAiOcrService::class)->resolveOcrModel();
        }

        return null;
    }

    /**
     * @param  Builder<\App\Models\TokenUsageLog>  $query
     * @return Builder<\App\Models\TokenUsageLog>
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

    private static function sumTokensUsed(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        $toonSum = (int) self::teamLogQuery($teamId, $from, $to)->where('used_toon', true)->sum('toon_tokens');
        $jsonSum = (int) self::teamLogQuery($teamId, $from, $to)->where('used_toon', false)->sum('json_tokens');

        return $toonSum + $jsonSum;
    }

    /**
     * One row per meeting summary, with the tokens already prepared for billing.
     *
     * @return list<array{id: ?int, label: string, tokens: int, prompt_tokens: int, completion_tokens: int, tokens_saved: int, at: ?string}>
     */
    public static function summaryLines(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $logs = self::teamLogQuery($teamId, $from, $to)
            ->where('service', 'MeetingSummaryService')
            ->orderByDesc('created_at')
            ->get();

        $rows = [];
        foreach ($logs as $log)
        {
            $billed = self::billedUsageForLog($log);
            $tokens = BilledUsage::used($billed);
            if ($tokens < 1)
            {
                continue;
            }

            $stored = is_array($log->billed_usage) ? $log->billed_usage : [];
            $title = trim((string) ($stored['label'] ?? ''));
            $meetingId = (int) ($stored['meeting_id'] ?? 0);
            $rows[] = [
                'id' => $meetingId > 0 ? $meetingId : null,
                'label' => $title !== '' ? 'Resumen · '.$title : 'Resumen de reunión',
                'tokens' => $tokens,
                'prompt_tokens' => (int) $billed['prompt_tokens'],
                'completion_tokens' => (int) $billed['completion_tokens'],
                'tokens_saved' => (int) $billed['tokens_saved'],
                'at' => $log->created_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array{module_name: string, count: int, tokens_used: int, tokens_saved: int}>
     */
    private static function callsByModule(int $teamId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $rows = self::teamLogQuery($teamId, $from, $to)
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
                    'amount_cents' => self::amountCentsByLogs($logs),
                ];
            })
            ->toArray();

        $serviceLogs = self::teamLogQuery($teamId, $from, $to)
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
                'amount_cents' => self::amountCentsByLogs($logs),
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
    private static function amountCentsByLogs(Collection $logs): int
    {
        $presenter = app(ClientTokenPresenter::class);

        return (int) $logs->sum(function (TokenUsageLog $log) use ($presenter): int
        {
            $billed = self::billedUsageForLog($log);

            return $presenter->costCents(
                $billed['prompt_tokens'],
                $billed['completion_tokens'],
                self::modelForLog($log, $billed),
                $log->created_at,
            );
        });
    }

    /**
     * @param  Collection<int, TokenUsageLog>  $logs
     */
    private static function tokensSavedBilledByLogs(Collection $logs): int
    {
        return (int) $logs->sum(fn (TokenUsageLog $log): int => self::billedUsageForLog($log)['tokens_saved']);
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
            $merged[$name]['amount_cents'] = ($merged[$name]['amount_cents'] ?? 0) + ($entry['amount_cents'] ?? 0);
        }

        return $merged;
    }
}
