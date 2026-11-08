<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AxisMenuPreferenceController extends Controller
{
    private const SETTING = 'axis_hidden_menu';

    /**
     * Sections the Axis menu can show or hide. Panel stays visible.
     *
     * @var list<string>
     */
    private const HREFS = [
        '/today',
        '/contact/list',
        '/client/list',
        '/list60/list',
        '/kanban',
        '/subscription',
        '/affiliate',
    ];

    public function show(Request $request): JsonResponse
    {
        $team = $this->team($request->user());
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'hidden' => $this->hidden($team),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $team = $this->team($user);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if (! $user->canManageTeam($team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tenés permiso para cambiar el menú.'),
            ], 403);
        }

        $validated = $request->validate([
            'hidden' => ['present', 'array'],
            'hidden.*' => ['string'],
        ]);

        $hidden = array_values(array_unique(array_filter(
            $validated['hidden'],
            fn (string $href): bool => in_array($href, self::HREFS, true),
        )));

        $team->setSetting(self::SETTING, $hidden, [
            'type' => 'json',
            'group' => 'axis',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'hidden' => $hidden,
            ],
        ]);
    }

    private function team(?User $user): Team|JsonResponse
    {
        $team = $user?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        return $team;
    }

    /**
     * @return list<string>
     */
    private function hidden(Team $team): array
    {
        $value = $team->getSetting(self::SETTING, []);
        if (! is_array($value))
        {
            return [];
        }

        return array_values(array_filter(
            $value,
            fn ($href): bool => is_string($href) && in_array($href, self::HREFS, true),
        ));
    }
}
