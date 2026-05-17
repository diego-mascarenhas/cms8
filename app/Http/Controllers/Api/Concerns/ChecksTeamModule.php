<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ChecksTeamModule
{
    protected function teamOrError(Request $request): Team|JsonResponse
    {
        $team = $request->user()?->currentTeam;

        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual. Selecciona un equipo en Humano.'),
            ], 422);
        }

        return $team;
    }

    protected function ensureTeamModule(Team $team, string $moduleKey): ?JsonResponse
    {
        if ($team->hasModule($moduleKey))
        {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => __('Este módulo no está disponible en tu plan.'),
        ], 403);
    }
}
