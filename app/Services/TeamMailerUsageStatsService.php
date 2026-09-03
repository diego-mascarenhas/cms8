<?php

namespace App\Services;

use App\Enums\TeamBillingProduct;
use App\Models\MailerUsageLog;
use App\Models\MessageDelivery;
use App\Models\Team;
use App\Models\TeamBillingRate;
use App\Support\MailerPaygPricing;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class TeamMailerUsageStatsService
{
    /**
     * @return array{
     *     emails_sent: int,
     *     amount_due_cents: int,
     *     our_rate: float,
     *     currency: string
     * }
     */
    public static function forTeam(Team $team, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $asOf = $to ?? now();
        $emails = self::sentCount($team, $from, $to);
        $ourRate = (float) MailerPaygPricing::pricePerEmail($team, $asOf);
        $amountDueCents = $from instanceof CarbonInterface
            ? self::amountCentsForWindows($team, Carbon::instance($from), Carbon::instance($asOf))
            : MailerPaygPricing::overageDueCents($emails, $team, $asOf);

        return [
            'emails_sent' => $emails,
            'amount_due_cents' => $amountDueCents,
            'our_rate' => $ourRate,
            'currency' => MailerPaygPricing::currency(),
        ];
    }

    private static function amountCentsForWindows(Team $team, Carbon $from, Carbon $to): int
    {
        $amount = 0.0;
        $windows = TeamBillingRate::windows((int) $team->id, TeamBillingProduct::MailerSend, $from, $to);
        $last = array_key_last($windows);
        foreach ($windows as $index => $window)
        {
            $amount += self::sentCount($team, $window['from'], $window['to'], $index !== $last) * $window['amount'];
        }

        return (int) round($amount * 100);
    }

    private static function sentCount(Team $team, ?CarbonInterface $from, ?CarbonInterface $to, bool $toExclusive = false): int
    {
        $deliveries = MessageDelivery::query()
            ->where('team_id', $team->id)
            ->whereNotNull('sent_at');

        $logs = MailerUsageLog::query()->where('team_id', $team->id);

        if ($from)
        {
            $deliveries->where('sent_at', '>=', $from);
            $logs->where('sent_at', '>=', $from);
        }

        if ($to)
        {
            $deliveries->where('sent_at', $toExclusive ? '<' : '<=', $to);
            $logs->where('sent_at', $toExclusive ? '<' : '<=', $to);
        }

        return $deliveries->count() + (int) $logs->sum('count');
    }
}
