<?php

namespace App\Http\Controllers\Api\Shop\Concerns;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ResolvesShopTeam
{
    use ChecksTeamModule;

    protected function shopTeam(Request $request, string $moduleKey): Team|JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, $moduleKey))
        {
            return $denied;
        }

        return $team;
    }

    protected function shopTeamWithAnyModule(Request $request): Team|JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        foreach (['products', 'stores', 'orders'] as $moduleKey)
        {
            if ($team->hasModule($moduleKey))
            {
                return $team;
            }
        }

        return response()->json([
            'success' => false,
            'message' => __('Este módulo no está disponible en tu plan.'),
        ], 403);
    }
}
