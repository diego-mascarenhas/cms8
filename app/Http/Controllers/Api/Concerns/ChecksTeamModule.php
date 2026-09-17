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

    /**
     * Best-effort enable of a catalog module for the Humano menu.
     *
     * Idoneo SPAs (Ads, Shop, Mailer, …) must keep working even when the
     * team flag is off or the catalog row is missing. Those flags only
     * control which items appear in the cms8/Humano menu.
     */
    protected function ensureTeamModule(Team $team, string $moduleKey): ?JsonResponse
    {
        if ($team->hasModule($moduleKey))
        {
            return null;
        }

        $team->enableModule($moduleKey);
        $team->unsetRelation('modules');

        return null;
    }
}
