<?php

namespace App\Services;

use App\Models\MeetingTranscript;
use App\Models\VoiceRecognitionUsageLog;
use Carbon\CarbonInterface;

final class VoiceRecognitionUsageRecorder
{
    public function record(
        int $teamId,
        int $seconds,
        string $source = 'app',
        ?int $userId = null,
        ?string $externalRef = null,
        ?CarbonInterface $recognizedAt = null,
    ): VoiceRecognitionUsageLog {
        $source = trim($source);
        $attributes = [
            'user_id' => $userId,
            'source' => $source !== '' ? mb_substr($source, 0, 64) : 'app',
            'seconds' => max(1, $seconds),
            'recognized_at' => $recognizedAt ?? now(),
        ];
        $externalRef = trim((string) $externalRef);

        if ($externalRef === '')
        {
            return VoiceRecognitionUsageLog::query()->create($attributes + [
                'team_id' => $teamId,
                'external_ref' => null,
            ]);
        }

        return VoiceRecognitionUsageLog::query()->updateOrCreate(
            [
                'team_id' => $teamId,
                'external_ref' => mb_substr($externalRef, 0, 160),
            ],
            $attributes,
        );
    }

    public function recordMeeting(MeetingTranscript $meeting): void
    {
        $secondsByUser = [];

        foreach ($meeting->turns ?? [] as $turn)
        {
            if (! is_array($turn) || ! is_numeric($turn['user_id'] ?? null))
            {
                continue;
            }

            $seconds = (int) ceil(max(0, (float) ($turn['end_seconds'] ?? 0) - (float) ($turn['start_seconds'] ?? 0)));
            if ($seconds < 1)
            {
                continue;
            }

            $userId = (int) $turn['user_id'];
            $secondsByUser[$userId] = ($secondsByUser[$userId] ?? 0) + $seconds;
        }

        $kept = [];
        foreach ($secondsByUser as $userId => $seconds)
        {
            $ref = 'meeting:'.$meeting->id.':user:'.$userId;
            $kept[] = $ref;
            $this->record(
                (int) $meeting->team_id,
                $seconds,
                'meetings',
                $userId,
                $ref,
                $meeting->created_at,
            );
        }

        $stale = VoiceRecognitionUsageLog::query()
            ->where('team_id', $meeting->team_id)
            ->where('source', 'meetings')
            ->where('external_ref', 'like', 'meeting:'.$meeting->id.':user:%');

        if ($kept !== [])
        {
            $stale->whereNotIn('external_ref', $kept);
        }

        $stale->delete();
    }
}
