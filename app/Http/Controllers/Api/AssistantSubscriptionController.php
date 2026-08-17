<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompleteAssistantCheckoutRequest;
use App\Http\Requests\Api\CreateAssistantCheckoutRequest;
use App\Services\Billing\AssistantSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantSubscriptionController extends Controller
{
    public function __construct(
        private AssistantSubscriptionService $subscriptions,
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

        return response()->json([
            'success' => true,
            'data' => $this->subscriptions->summary($team),
        ]);
    }

    public function checkout(CreateAssistantCheckoutRequest $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $result = $this->subscriptions->createCheckout(
            $team,
            (string) $request->validated('interval'),
            (string) $request->validated('success_url'),
            (string) $request->validated('cancel_url'),
        );

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? __('No se pudo crear el checkout.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'url' => $result['url'],
        ]);
    }

    public function complete(CompleteAssistantCheckoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;
        $result = $this->subscriptions->completeCheckout(
            $team,
            (string) $request->validated('session_id'),
            (int) $user->id,
        );

        if (! $result['success'])
        {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? __('No se pudo completar el checkout.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }
}
