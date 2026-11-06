<?php

namespace App\Services;

use App\Enums\TeamBillingProduct;
use App\Models\ProspectUsageLog;
use App\Models\Team;
use App\Models\TeamBillingRate;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class TeamProspectUsageStatsService
{
    /**
     * @return array{
     *     credits_used: int,
     *     our_amount_cents: int,
     *     our_rate: float,
     *     currency: string
     * }
     */
    public static function forTeam(Team $team, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $credits = self::creditCount($team, $from, $to);
        $asOf = $to ?? now();
        $ourRate = TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::ProspectCredit, $asOf);
        $currency = strtoupper((string) config('humano_pricing.prospect_billing.currency', 'USD'));

        $ourCents = $from instanceof CarbonInterface
            ? self::amountCentsForWindows($team, Carbon::instance($from), Carbon::instance($asOf))
            : (int) round($credits * $ourRate * 100);

        return [
            'credits_used' => $credits,
            'our_amount_cents' => $ourCents,
            'our_rate' => $ourRate,
            'currency' => $currency,
        ];
    }

    /**
     * @return list<array{label: string, count: int, amount_cents: int, at: ?string}>
     */
    public static function lines(Team $team, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $query = ProspectUsageLog::query()->where('team_id', $team->id);
        if ($from)
        {
            $query->where('consumed_at', '>=', $from);
        }
        if ($to)
        {
            $query->where('consumed_at', '<=', $to);
        }

        $groups = [];
        foreach ($query->get(['source', 'count', 'consumed_at']) as $row)
        {
            $source = trim((string) $row->source) ?: 'import';
            $groups[$source] ??= [
                'label' => self::sourceLabel($source),
                'count' => 0,
                'weight' => 0.0,
                'at' => null,
            ];
            $count = max(0, (int) $row->count);
            $groups[$source]['count'] += $count;
            $at = $row->consumed_at ?? now();
            $rate = TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::ProspectCredit, $at);
            $groups[$source]['weight'] += $count * $rate;
            $iso = $at->toIso8601String();
            if ($groups[$source]['at'] === null || $iso > $groups[$source]['at'])
            {
                $groups[$source]['at'] = $iso;
            }
        }

        $rows = array_values($groups);
        usort($rows, fn (array $left, array $right): int => $right['weight'] <=> $left['weight']);
        $cents = self::allocateCents(array_column($rows, 'weight'), (int) round(array_sum(array_column($rows, 'weight')) * 100));

        return array_map(function (array $row, int $amount): array
        {
            unset($row['weight']);

            return $row + ['amount_cents' => $amount];
        }, $rows, $cents);
    }

    private static function sourceLabel(string $source): string
    {
        return match ($source)
        {
            'import' => 'Importación',
            'app' => 'App',
            default => ucfirst(str_replace(['_', '-'], ' ', $source)),
        };
    }

    /**
     * @param  list<int|float>  $weights
     * @return list<int>
     */
    private static function allocateCents(array $weights, int $totalCents): array
    {
        $cents = array_fill(0, count($weights), 0);
        $sum = array_sum($weights);
        if ($sum <= 0 || $totalCents <= 0)
        {
            return $cents;
        }

        $fractions = [];
        $assigned = 0;
        foreach (array_values($weights) as $index => $weight)
        {
            $exact = $totalCents * ($weight / $sum);
            $cents[$index] = (int) floor($exact);
            $fractions[$index] = $exact - $cents[$index];
            $assigned += $cents[$index];
        }

        arsort($fractions);
        foreach (array_keys($fractions) as $index)
        {
            if ($assigned >= $totalCents)
            {
                break;
            }

            $cents[$index]++;
            $assigned++;
        }

        return $cents;
    }

    private static function amountCentsForWindows(Team $team, Carbon $from, Carbon $to): int
    {
        $amount = 0.0;
        $windows = TeamBillingRate::windows((int) $team->id, TeamBillingProduct::ProspectCredit, $from, $to);
        $last = array_key_last($windows);
        foreach ($windows as $index => $window)
        {
            $amount += self::creditCount($team, $window['from'], $window['to'], $index !== $last) * $window['amount'];
        }

        return (int) round($amount * 100);
    }

    private static function creditCount(Team $team, ?CarbonInterface $from, ?CarbonInterface $to, bool $toExclusive = false): int
    {
        $query = ProspectUsageLog::query()->where('team_id', $team->id);

        if ($from)
        {
            $query->where('consumed_at', '>=', $from);
        }

        if ($to)
        {
            $query->where('consumed_at', $toExclusive ? '<' : '<=', $to);
        }

        return (int) $query->sum('count');
    }
}
