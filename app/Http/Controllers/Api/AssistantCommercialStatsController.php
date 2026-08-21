<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssistantCommercialStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantCommercialStatsController extends Controller
{
    public function __construct(
        private readonly AssistantCommercialStatsService $stats,
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
            'data' => $this->stats->forTeam($team),
        ]);
    }
}
