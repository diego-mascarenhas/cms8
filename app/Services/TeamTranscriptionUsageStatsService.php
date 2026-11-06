<?php

namespace App\Services;

use App\Enums\MeetingTranscriptStatus;
use App\Enums\TeamBillingProduct;
use App\Models\MeetingTranscript;
use App\Models\Team;
use App\Models\TeamBillingRate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class TeamTranscriptionUsageStatsService
{
    /**
     * @return array{
     *     audio_seconds: int,
     *     our_amount_cents: int,
     *     our_rate: float,
     *     currency: string,
     *     formatted_duration: string
     * }
     */
    public static function forTeam(Team $team, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $asOf = $to ?? now();
        $totals = self::totals($team, $from, $to);
        $ourRate = TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::TranscriptionMinute, $asOf);
        $currency = strtoupper((string) config('humano_pricing.transcription_billing.currency', 'USD'));

        return [
            'audio_seconds' => $totals['seconds'],
            'our_amount_cents' => $totals['cents'],
            'our_rate' => $ourRate,
            'currency' => $currency,
            'formatted_duration' => self::formatDuration($totals['seconds']),
        ];
    }

    /**
     * @return list<array{label: string, count: int, amount_cents: int, at: ?string}>
     */
    public static function lines(Team $team, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $rows = [];
        $weights = [];

        foreach (self::readyMeetings($team, $from, $to)->orderByDesc('created_at')->get(['title', 'turns', 'created_at']) as $meeting)
        {
            $audio = $meeting->billedAudioSeconds();
            if ($audio < 1)
            {
                continue;
            }

            $at = $meeting->created_at ?? now();
            $rate = TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::TranscriptionMinute, $at);
            $rows[] = [
                'label' => (string) ($meeting->title ?: 'Reunión'),
                'count' => $audio,
                'at' => $meeting->created_at?->toIso8601String(),
            ];
            $weights[] = ($audio / 60) * $rate;
        }

        $cents = self::allocateCents($weights, (int) round(array_sum($weights) * 100));

        return array_map(function (array $row, int $amount): array
        {
            return $row + ['amount_cents' => $amount];
        }, $rows, $cents);
    }

    public static function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds < 60)
        {
            return $seconds.' s';
        }

        if ($seconds < 3600)
        {
            return self::trimNumber($seconds / 60).' min';
        }

        return self::trimNumber($seconds / 3600).' h';
    }

    private static function readyMeetings(Team $team, ?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        $query = MeetingTranscript::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('status', MeetingTranscriptStatus::Ready);

        if ($from)
        {
            $query->where('created_at', '>=', $from);
        }

        if ($to)
        {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * The rate is the client price per minute already in force, margin included.
     *
     * @return array{seconds: int, cents: int}
     */
    private static function totals(Team $team, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $seconds = 0;
        $amount = 0.0;

        foreach (self::readyMeetings($team, $from, $to)->get(['turns', 'created_at']) as $meeting)
        {
            $audio = $meeting->billedAudioSeconds();
            $seconds += $audio;
            $at = $meeting->created_at ?? now();
            $rate = TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::TranscriptionMinute, $at);
            $amount += ($audio / 60) * $rate;
        }

        return [
            'seconds' => $seconds,
            'cents' => (int) round($amount * 100),
        ];
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

    private static function trimNumber(float $value): string
    {
        $formatted = number_format($value, 1, ',', '.');

        return str_ends_with($formatted, ',0') ? substr($formatted, 0, -2) : $formatted;
    }
}
