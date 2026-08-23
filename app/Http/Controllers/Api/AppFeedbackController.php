<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAppFeedbackRequest;
use App\Models\AppFeedback;
use App\Support\AppFeedbackQuestions;
use Illuminate\Http\JsonResponse;

class AppFeedbackController extends Controller
{
    use ChecksTeamModule;

    public function store(StoreAppFeedbackRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $answers = array_values($request->validated('answers'));
        $comment = $request->validated('comment');

        $feedback = AppFeedback::query()->create([
            'team_id' => $team->id,
            'user_id' => $request->user()?->id,
            'product' => $request->validated('product'),
            'answers' => $answers,
            'comment' => $comment,
            'message' => AppFeedbackQuestions::summarize($answers, $comment),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Gracias. Recibimos tu feedback.'),
            'data' => [
                'id' => $feedback->id,
            ],
        ], 201);
    }
}
