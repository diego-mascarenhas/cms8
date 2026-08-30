<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IdentifySiteAssistantVisitorRequest;
use App\Models\Automation;
use App\Services\AssistantAutomationRunner;
use App\Services\SiteAssistantConversationService;
use App\Services\SiteAssistantInboxIdentityService;
use App\Services\SiteAssistantVisitorIdentityService;
use App\Services\TeamSiteAssistantPromptService;
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
    public function chat(
        Request $request,
        string $token,
        AssistantAutomationRunner $runner,
        SiteAssistantVisitorIdentityService $identity,
        SiteAssistantConversationService $conversations,
        TeamSiteAssistantPromptService $siteAssistant,
        SiteAssistantInboxIdentityService $inboxIdentity,
    ): JsonResponse {
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

        $automation->loadMissing('team');
        $handled = $inboxIdentity->handlePublicMessage(
            $automation,
            $sessionKey,
            $validated['message'],
            $identity,
            $conversations,
        );
        if ($handled !== null)
        {
            return response()->json([
                'success' => true,
                'reply' => $handled['reply'],
                'response' => $handled['reply'],
                'routed_to' => null,
                'automation_slug' => $automation->slug,
                'step_key' => null,
                'flow_completed' => false,
                'session_key' => $sessionKey,
                'visitor' => $handled['visitor'],
                'demo' => false,
            ]);
        }

        $visitor = $identity->visitorFor($automation, $sessionKey);
        $sessionPromptKey = $conversations->inboundPromptKeyFor(
            $automation,
            $sessionKey,
            $visitor['contact_id'] ?? null,
        );
        if ($automation->team && ! $siteAssistant->allowsPublicEmbedReply(
            $automation->team,
            $visitor['contact_id'] ?? null,
            $sessionPromptKey,
        ))
        {
            $conversations->recordTurn(
                $automation,
                $sessionKey,
                $validated['message'],
                '',
            );

            return response()->json([
                'success' => true,
                'reply' => '',
                'response' => '',
                'routed_to' => null,
                'automation_slug' => $automation->slug,
                'step_key' => null,
                'flow_completed' => false,
                'session_key' => $sessionKey,
                'visitor' => $identity->publicVisitor($visitor),
                'demo' => false,
            ]);
        }

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

        $conversations->recordTurn(
            $automation,
            $sessionKey,
            $validated['message'],
            (string) ($result['response'] ?? ''),
        );

        return response()->json([
            'success' => true,
            'reply' => $result['response'],
            'response' => $result['response'],
            'routed_to' => $result['routed_to'] ?? null,
            'automation_slug' => $automation->slug,
            'step_key' => $result['step_key'] ?? null,
            'flow_completed' => (bool) ($result['flow_completed'] ?? false),
            'session_key' => $sessionKey,
            'visitor' => $identity->publicVisitor($identity->visitorFor($automation, $sessionKey)),
            'demo' => false,
        ]);
    }

    public function messages(
        Request $request,
        string $token,
        AssistantAutomationRunner $runner,
        SiteAssistantConversationService $conversations,
        SiteAssistantVisitorIdentityService $identity,
    ): JsonResponse {
        $validated = $request->validate([
            'session_key' => 'required|string|max:191',
            'after_id' => 'nullable|integer|min:0',
        ]);

        $automation = $runner->findByPublicToken($token);
        if (! $automation)
        {
            return response()->json([
                'success' => false,
                'message' => __('Automation not found.'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'session_key' => $validated['session_key'],
            'messages' => $conversations->publicMessagesForSession(
                $automation,
                $validated['session_key'],
                (int) ($validated['after_id'] ?? 0),
            ),
            'visitor' => $identity->publicVisitor(
                $identity->visitorFor($automation, $validated['session_key']),
            ),
        ]);
    }

    public function identify(
        IdentifySiteAssistantVisitorRequest $request,
        string $token,
        AssistantAutomationRunner $runner,
        SiteAssistantVisitorIdentityService $identity,
    ): JsonResponse {
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
            $runner->requireActive($automation, Automation::CHANNEL_API);
        } catch (NotFoundHttpException $e)
        {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (AccessDeniedHttpException $e)
        {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        $validated = $request->validated();
        $sessionKey = isset($validated['session_key']) && trim((string) $validated['session_key']) !== ''
            ? trim((string) $validated['session_key'])
            : (string) Str::uuid();

        $visitor = $identity->identify(
            $automation,
            $sessionKey,
            (string) $validated['email'],
            isset($validated['name']) ? (string) $validated['name'] : null,
            isset($validated['phone']) ? (string) $validated['phone'] : null,
        );

        return response()->json([
            'success' => true,
            'session_key' => $sessionKey,
            'visitor' => $identity->publicVisitor($visitor),
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
