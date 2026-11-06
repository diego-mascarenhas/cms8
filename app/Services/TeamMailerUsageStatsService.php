<?php

namespace App\Services;

use App\Enums\TeamBillingProduct;
use App\Models\MailerUsageLog;
use App\Models\Message;
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

    /**
     * @return list<array{label: string, count: int, amount_cents: int, at: ?string}>
     */
    public static function lines(Team $team, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $groups = [];

        $deliveries = MessageDelivery::query()
            ->where('team_id', $team->id)
            ->whereNotNull('sent_at');
        $logs = MailerUsageLog::query()->where('team_id', $team->id);
        self::applySentWindow($deliveries, $from, $to);
        self::applySentWindow($logs, $from, $to);

        $deliveryRows = $deliveries->get(['message_id', 'sent_at']);
        $names = Message::query()
            ->whereIn('id', $deliveryRows->pluck('message_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        foreach ($deliveryRows as $row)
        {
            $key = 'delivery:'.($row->message_id ?? 0);
            $groups[$key] ??= [
                'label' => (string) ($names[$row->message_id] ?? 'Envío'),
                'count' => 0,
                'weight' => 0.0,
                'at' => null,
            ];
            $groups[$key]['count']++;
            $sentAt = $row->sent_at ?? now();
            $groups[$key]['weight'] += (float) MailerPaygPricing::pricePerEmail($team, $sentAt);
            $groups[$key]['at'] = self::laterIso($groups[$key]['at'], $sentAt);
        }

        foreach ($logs->get(['source', 'count', 'sent_at']) as $row)
        {
            $source = trim((string) $row->source) ?: 'app';
            $key = 'log:'.$source;
            $groups[$key] ??= [
                'label' => self::sourceLabel($source),
                'count' => 0,
                'weight' => 0.0,
                'at' => null,
            ];
            $count = max(0, (int) $row->count);
            $groups[$key]['count'] += $count;
            $sentAt = $row->sent_at ?? now();
            $groups[$key]['weight'] += $count * (float) MailerPaygPricing::pricePerEmail($team, $sentAt);
            $groups[$key]['at'] = self::laterIso($groups[$key]['at'], $sentAt);
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

    private static function applySentWindow($query, ?CarbonInterface $from, ?CarbonInterface $to, bool $toExclusive = false): void
    {
        if ($from)
        {
            $query->where('sent_at', '>=', $from);
        }

        if ($to)
        {
            $query->where('sent_at', $toExclusive ? '<' : '<=', $to);
        }
    }

    private static function sourceLabel(string $source): string
    {
        return match ($source)
        {
            'communications' => 'Comunicaciones',
            'import' => 'Importación',
            'app' => 'App',
            'mailer' => 'Mailer',
            'assistant' => 'Assistant',
            'estimator' => 'Presupuestos',
            default => ucfirst(str_replace(['_', '-'], ' ', $source)),
        };
    }

    private static function laterIso(?string $current, CarbonInterface $at): string
    {
        $next = $at->toIso8601String();
        if ($current === null || $next > $current)
        {
            return $next;
        }

        return $current;
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
}
