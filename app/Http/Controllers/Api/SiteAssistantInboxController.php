<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendSiteAssistantInboxReplyRequest;
use App\Http\Requests\Api\UpdateSiteAssistantThreadPromptRequest;
use App\Models\Prompt;
use App\Services\AssistantPromptCatalog;
use App\Services\SiteAssistantConversationService;
use App\Services\TeamSiteAssistantPromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SiteAssistantInboxController extends Controller
{
    public function index(Request $request, SiteAssistantConversationService $conversations): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $limit = (int) $request->integer('limit', 30);
        $allowed = $conversations->allowedContactIdsFor($request->user());
        $payload = $conversations->listForTeam($team, $limit, $allowed);

        return response()->json([
            'success' => true,
            'conversations' => $payload['conversations'],
            'total' => $payload['total'],
        ]);
    }

    public function show(Request $request, string $sessionKey, SiteAssistantConversationService $conversations): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $allowed = $conversations->allowedContactIdsFor($request->user());
        $thread = $conversations->threadForTeam($team, $sessionKey, $allowed);
        if (! $thread)
        {
            return response()->json([
                'success' => false,
                'message' => __('Conversation not found.'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            ...$thread,
        ]);
    }

    public function store(
        SendSiteAssistantInboxReplyRequest $request,
        string $sessionKey,
        SiteAssistantConversationService $conversations,
    ): JsonResponse {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $allowed = $conversations->allowedContactIdsFor($request->user());
        $message = $conversations->replyAsStaff(
            $team,
            $sessionKey,
            (string) $request->validated('message'),
            $allowed,
            $request->user(),
        );
        if (! $message)
        {
            return response()->json([
                'success' => false,
                'message' => __('Conversation not found.'),
            ], 404);
        }

        $thread = $conversations->threadForTeam($team, $sessionKey, $allowed);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'role' => $message->role,
                'body' => $message->body,
                'created_at' => optional($message->created_at)?->toIso8601String(),
                'user_id' => $message->user_id,
            ],
            ...($thread ?? []),
        ]);
    }

    public function updateAssistant(
        UpdateSiteAssistantThreadPromptRequest $request,
        string $sessionKey,
        SiteAssistantConversationService $conversations,
        TeamSiteAssistantPromptService $siteAssistant,
    ): JsonResponse {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $allowed = $conversations->allowedContactIdsFor($request->user());
        $hasPromptKey = $request->exists('prompt_key');
        $promptKey = $hasPromptKey ? trim((string) $request->input('prompt_key', '')) : null;

        if ($hasPromptKey && $promptKey !== '')
        {
            try
            {
                $promptKey = app(AssistantPromptCatalog::class)->ensureOnTeam($team, $promptKey);
            } catch (InvalidArgumentException $exception)
            {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            $prompt = Prompt::findByRoutingKey($promptKey, (int) $team->id);
            if (! $prompt || ! $prompt->is_active)
            {
                return response()->json([
                    'success' => false,
                    'message' => __('team_settings.site_assistant.invalid_prompt'),
                ], 422);
            }

            $pinned = $conversations->pinInboundPrompt(
                $team,
                $sessionKey,
                $siteAssistant->routingKeyFor($prompt),
                true,
                $allowed,
            );
        } elseif ($hasPromptKey)
        {
            $pinned = $conversations->pinInboundPrompt(
                $team,
                $sessionKey,
                null,
                $request->boolean('on', false),
                $allowed,
            );
        } else
        {
            $pinned = $conversations->pinInboundPrompt(
                $team,
                $sessionKey,
                null,
                $request->boolean('on'),
                $allowed,
            );
        }

        if (! $pinned)
        {
            return response()->json([
                'success' => false,
                'message' => __('Conversation not found.'),
            ], 404);
        }

        $thread = $conversations->threadForTeam($team, $sessionKey, $allowed);

        return response()->json([
            'success' => true,
            ...($thread ?? []),
        ]);
    }
}
