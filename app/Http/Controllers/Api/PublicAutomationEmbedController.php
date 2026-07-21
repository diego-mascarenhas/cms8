<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Services\AssistantAutomationRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public embed endpoints for a team automation (authenticated by public_token).
 */
class PublicAutomationEmbedController extends Controller
{
    public function chat(Request $request, string $token, AssistantAutomationRunner $runner): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'session_key' => 'nullable|string|max:191',
        ]);

        $automation = $runner->findByPublicToken($token);
        if (! $automation)
        {
            return response()->json([
                'success' => false,
                'message' => __('Automation not found.'),
            ], 404);
        }

        $sessionKey = isset($validated['session_key']) && trim($validated['session_key']) !== ''
            ? trim($validated['session_key'])
            : (string) Str::uuid();

        try
        {
            $result = $runner->run(
                $automation,
                Automation::CHANNEL_API,
                $validated['message'],
                null,
                null,
                false,
                $sessionKey,
            );
        } catch (NotFoundHttpException $e)
        {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e)
        {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        return response()->json([
            'success' => true,
            'reply' => $result['response'],
            'response' => $result['response'],
            'routed_to' => $result['routed_to'] ?? null,
            'automation_slug' => $automation->slug,
            'step_key' => $result['step_key'] ?? null,
            'flow_completed' => (bool) ($result['flow_completed'] ?? false),
            'session_key' => $sessionKey,
            'demo' => false,
        ]);
    }

    public function meta(string $token, AssistantAutomationRunner $runner): JsonResponse
    {
        $automation = $runner->findByPublicToken($token);
        if (! $automation || ! $automation->is_active)
        {
            return response()->json([
                'success' => false,
                'message' => __('Automation not found.'),
            ], 404);
        }

        if (! $automation->allowsChannel(Automation::CHANNEL_API))
        {
            return response()->json([
                'success' => false,
                'message' => __('This automation does not allow the API channel.'),
            ], 403);
        }

        $welcome = is_array($automation->settings)
            ? ($automation->settings['welcome_message'] ?? null)
            : null;

        return response()->json([
            'success' => true,
            'name' => $automation->name,
            'slug' => $automation->slug,
            'welcome_message' => $welcome,
            'has_flow' => $automation->hasFlowGraph(),
        ]);
    }
}
