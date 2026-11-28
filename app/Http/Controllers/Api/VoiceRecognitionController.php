<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RecordVoiceRecognitionUsageRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamVoiceRecognitionUsageStatsService;
use App\Services\VoiceRecognitionUsageRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceRecognitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $team = $this->team($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $voices = User::query()
            ->whereNotNull('voice_speaker_id')
            ->where(function ($query) use ($team): void
            {
                $query->whereHas('teams', function ($teams) use ($team): void
                {
                    $teams->where('teams.id', $team->id);
                })->orWhereHas('ownedTeams', function ($teams) use ($team): void
                {
                    $teams->where('teams.id', $team->id);
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'voice_speaker_id']);

        return response()->json([
            'success' => true,
            'data' => $voices->map(fn (User $user): array => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'speaker_id' => $user->voice_speaker_id,
            ])->values(),
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $team = $this->team($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return response()->json([
            'success' => true,
            'data' => TeamVoiceRecognitionUsageStatsService::forTeam($team),
        ]);
    }

    public function storeUsage(RecordVoiceRecognitionUsageRequest $request, VoiceRecognitionUsageRecorder $recorder): JsonResponse
    {
        $team = $this->team($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $userId = $request->validated('user_id');
        if ($userId !== null && ! $this->userBelongsToTeam($team, (int) $userId))
        {
            return response()->json([
                'success' => false,
                'message' => __('Esa persona no pertenece al equipo.'),
            ], 422);
        }

        $log = $recorder->record(
            (int) $team->id,
            (int) $request->validated('seconds'),
            (string) ($request->validated('source') ?? 'app'),
            $userId !== null ? (int) $userId : null,
            $request->validated('external_ref'),
            $request->date('recognized_at'),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'source' => $log->source,
                'external_ref' => $log->external_ref,
                'seconds' => $log->seconds,
                'recognized_at' => $log->recognized_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function team(Request $request): Team|JsonResponse
    {
        $team = $request->attributes->get('team');
        if ($team instanceof Team)
        {
            return $team;
        }

        $team = $request->user()?->currentTeam;
        if (! $team instanceof Team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        return $team;
    }

    private function userBelongsToTeam(Team $team, int $userId): bool
    {
        if ((int) $team->user_id === $userId)
        {
            return true;
        }

        return $team->users()->where('users.id', $userId)->exists();
    }
}
