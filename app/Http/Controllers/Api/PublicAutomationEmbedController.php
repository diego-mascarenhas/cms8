<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Services\AssistantAutomationRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ]);

        $automation = $runner->findByPublicToken($token);
        if (! $automation)
        {
            return response()->json([
                'success' => false,
                'message' => __('Automation not found.'),
            ], 404);
        }

        try
        {
            $result = $runner->run(
                $automation,
                Automation::CHANNEL_API,
                $validated['message'],
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
        ]);
    }
}
