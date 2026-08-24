<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuggestList60InboxDraftRequest;
use App\Http\Requests\UpdateList60InboxPromptRequest;
use App\Services\List60InboxReviewService;
use App\Services\List60OutreachSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AssistantList60PromptController extends Controller
{
    public function __construct(
        private readonly List60InboxReviewService $review,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $this->authorize('update', $team);

        return response()->json([
            'success' => true,
            'data' => $this->review->promptPayload($team),
        ]);
    }

    public function update(UpdateList60InboxPromptRequest $request): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        try
        {
            $this->review->updateInstruction($team, $request->validated('prompt_instruction'));
        } catch (InvalidArgumentException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('team_settings.list60_prompt.updated'),
            'data' => $this->review->promptPayload($team->fresh()),
        ]);
    }

    public function review(Request $request): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $this->authorize('update', $team);

        try
        {
            $data = $this->review->review($request->user());
        } catch (InvalidArgumentException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function suggest(SuggestList60InboxDraftRequest $request, List60OutreachSuggestionService $suggestions): JsonResponse
    {
        $result = $suggestions->suggestWhatsAppForPhone($request->user(), $request->validated('phone'));
        if (! $result['success'])
        {
            $status = ! empty($result['not_found']) ? 404 : 422;

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? __('Error'),
            ], $status);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $result['message'] ?? '',
            ],
        ]);
    }
}
