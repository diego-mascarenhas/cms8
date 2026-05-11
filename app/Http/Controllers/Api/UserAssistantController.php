<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\AssistantChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Team assistant chat for mobile/API clients authenticated with Sanctum.
 * Uses the authenticated user's {@see User::$current_team_id} (same context as the web app).
 */
class UserAssistantController extends Controller
{
    /**
     * Chat with the assistant (router + flows). Uses Sanctum user auth.
     */
    public function chat(Request $request, AssistantChatService $assistant): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:16000',
            'prompt_key' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $teamId = $user->current_team_id;

        if (! $teamId)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual. Selecciona un equipo en Humano.'),
            ], 422);
        }

        $team = Team::query()->find($teamId);

        if ($team === null || ! $user->belongsToTeam($team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tenés acceso a este equipo.'),
            ], 403);
        }

        $promptKeyRaw = $request->input('prompt_key');
        $promptKey = \is_string($promptKeyRaw) && trim($promptKeyRaw) !== ''
            ? trim($promptKeyRaw)
            : null;

        $result = $assistant->run(
            $request->input('message'),
            (int) $teamId,
            null,
            null,
            false,
            $promptKey,
        );

        $payload = [
            'success' => true,
            'response' => $result['response'],
            'routed_to' => $result['routed_to'] ?? null,
        ];
        if (! empty($result['audio_base64']))
        {
            $payload['audio_base64'] = $result['audio_base64'];
            $payload['audio_mime'] = $result['audio_mime'] ?? 'audio/mpeg';
        }

        return response()->json($payload);
    }
}
