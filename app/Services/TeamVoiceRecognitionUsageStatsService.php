<?php

namespace App\Services;

use App\Enums\TeamBillingProduct;
use App\Models\Team;
use App\Models\TeamBillingRate;
use App\Models\VoiceRecognitionUsageLog;
use Carbon\CarbonInterface;

final class TeamVoiceRecognitionUsageStatsService
{
    /**
     * Recognized speech is priced with the transcription minute already in force.
     *
     * @return array{
     *     seconds: int,
     *     our_amount_cents: int,
     *     our_rate: float,
     *     currency: string,
     *     formatted_duration: string
     * }
     */
    public static function forTeam(Team $team, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $asOf = $to ?? now();
        $seconds = 0;
        $amount = 0.0;

        $query = VoiceRecognitionUsageLog::query()->where('team_id', $team->id);
        if ($from)
        {
            $query->where('recognized_at', '>=', $from);
        }
        if ($to)
        {
            $query->where('recognized_at', '<=', $to);
        }

        foreach ($query->get(['seconds', 'recognized_at']) as $log)
        {
            $audio = (int) $log->seconds;
            $seconds += $audio;
            $at = $log->recognized_at ?? now();
            $rate = TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::TranscriptionMinute, $at);
            $amount += ($audio / 60) * $rate;
        }

        return [
            'seconds' => $seconds,
            'our_amount_cents' => (int) round($amount * 100),
            'our_rate' => TeamBillingRate::amountOn((int) $team->id, TeamBillingProduct::TranscriptionMinute, $asOf),
            'currency' => strtoupper((string) config('humano_pricing.transcription_billing.currency', 'USD')),
            'formatted_duration' => TeamTranscriptionUsageStatsService::formatDuration($seconds),
        ];
    }
}
