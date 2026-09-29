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
        $currency = strtoupper((string) config('humano_pricing.prospect_billing.currency', 'EUR'));

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
