<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Services\AssistantAutomationRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TeamAssistantController extends Controller
{
    /**
     * Chat with the assistant (router + flows). Uses team token auth.
     * Optional automation_slug / automation_id packages a prompt + channel policy.
     */
    public function chat(Request $request, AssistantAutomationRunner $runner): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:16000',
            'prompt_key' => 'nullable|string|max:255',
            'automation_slug' => 'nullable|string|max:255',
            'automation_id' => 'nullable|integer|min:1',
            'session_key' => 'nullable|string|max:191',
        ]);

        $teamId = $request->get('team_id');
        if (! $teamId)
        {
            return response()->json(['success' => false, 'message' => 'Team not found'], 401);
        }

        $promptKeyRaw = $request->input('prompt_key');
        $promptKey = \is_string($promptKeyRaw) && trim($promptKeyRaw) !== ''
            ? trim($promptKeyRaw)
            : null;

        $automationSlug = $request->input('automation_slug');
        $automationId = $request->filled('automation_id') ? (int) $request->input('automation_id') : null;
        $sessionKey = $request->filled('session_key') ? trim((string) $request->input('session_key')) : null;

        try
        {
            $result = $runner->runForTeam(
                (int) $teamId,
                Automation::CHANNEL_API,
                $request->input('message'),
                \is_string($automationSlug) ? $automationSlug : null,
                $automationId,
                $promptKey,
                $sessionKey,
            );
        } catch (NotFoundHttpException $e)
        {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e)
        {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        $payload = [
            'success' => true,
            'response' => $result['response'],
            'routed_to' => $result['routed_to'] ?? null,
        ];

        if (! empty($result['automation_id']))
        {
            $payload['automation_id'] = $result['automation_id'];
            $payload['automation_slug'] = $result['automation_slug'] ?? null;
        }

        if (array_key_exists('step_key', $result))
        {
            $payload['step_key'] = $result['step_key'];
        }
        if (! empty($result['flow_completed']))
        {
            $payload['flow_completed'] = true;
        }

        if (! empty($result['audio_base64']))
        {
            $payload['audio_base64'] = $result['audio_base64'];
            $payload['audio_mime'] = $result['audio_mime'] ?? 'audio/mpeg';
        }

        return response()->json($payload);
    }
}
