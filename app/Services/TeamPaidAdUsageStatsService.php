<?php

namespace App\Services;

use App\Enums\PaidAdUsageKind;
use App\Enums\TeamBillingProduct;
use App\Models\PaidAdUsageLog;
use App\Models\Team;
use App\Models\TeamBillingRate;
use Carbon\CarbonInterface;

final class TeamPaidAdUsageStatsService
{
    /**
     * @return array{
     *     characters: int,
     *     voice_amount_cents: int,
     *     voice_rate: float,
     *     videos: int,
     *     video_amount_cents: int,
     *     video_rate: float,
     *     currency: string
     * }
     */
    public static function forTeam(Team $team, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $asOf = $to ?? now();
        $totals = self::totals($team, $from, $to);

        return [
            'characters' => $totals['characters'],
            'voice_amount_cents' => $totals['voice_cents'],
            'voice_rate' => TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::ElevenlabsVoiceThousand, $asOf),
            'videos' => $totals['videos'],
            'video_amount_cents' => $totals['video_cents'],
            'video_rate' => TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::PaidAdVideo, $asOf),
            'currency' => strtoupper((string) config('humano_pricing.elevenlabs_voice_billing.currency', 'USD')),
        ];
    }

    /**
     * The rates are the client prices already in force, margin included.
     *
     * @return array{characters: int, voice_cents: int, videos: int, video_cents: int}
     */
    private static function totals(Team $team, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $query = PaidAdUsageLog::query()->where('team_id', $team->id);

        if ($from)
        {
            $query->where('created_at', '>=', $from);
        }

        if ($to)
        {
            $query->where('created_at', '<=', $to);
        }

        $characters = 0;
        $videos = 0;
        $voiceAmount = 0.0;
        $videoAmount = 0.0;

        foreach ($query->get(['kind', 'quantity', 'created_at']) as $log)
        {
            $at = $log->created_at ?? now();
            $quantity = (int) $log->quantity;

            if ($log->kind === PaidAdUsageKind::Voice)
            {
                $characters += $quantity;
                $rate = TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::ElevenlabsVoiceThousand, $at);
                $voiceAmount += ($quantity / 1000) * $rate;
            }

            if ($log->kind === PaidAdUsageKind::Video)
            {
                $videos += $quantity;
                $rate = TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::PaidAdVideo, $at);
                $videoAmount += $quantity * $rate;
            }
        }

        return [
            'characters' => $characters,
            'voice_cents' => (int) round($voiceAmount * 100),
            'videos' => $videos,
            'video_cents' => (int) round($videoAmount * 100),
        ];
    }
}
