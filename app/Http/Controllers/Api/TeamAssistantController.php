<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssistantChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamAssistantController extends Controller
{
    /**
     * Chat with the assistant (router + flows). Uses team token auth.
     */
    public function chat(Request $request, AssistantChatService $assistant): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:16000',
            'prompt_key' => 'nullable|string|max:255',
        ]);

        $teamId = $request->get('team_id');
        if (! $teamId)
        {
            return response()->json(['success' => false, 'message' => 'Team not found'], 401);
        }

        $result = $assistant->run(
            $request->input('message'),
            (int) $teamId,
            null,
            null,
            false,
            $request->input('prompt_key') ?: null,
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
