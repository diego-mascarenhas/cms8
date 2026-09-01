<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompleteAiRequest;
use App\Services\AiCompletionService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AiCompletionController extends Controller
{
    public function __construct(
        private AiCompletionService $completions,
    ) {}

    public function complete(CompleteAiRequest $request): JsonResponse
    {
        $team = $request->user()->currentTeam;

        try
        {
            $result = $this->completions->complete(
                $team,
                (string) $request->validated('prompt'),
                (string) $request->validated('module'),
                $request->validated('service'),
                $request->validated('max_tokens') !== null ? (int) $request->validated('max_tokens') : null,
                $request->validated('temperature') !== null ? (float) $request->validated('temperature') : null,
            );
        } catch (RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
