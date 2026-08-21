<?php

namespace App\Http\Controllers\Api;

use App\Helpers\MenuHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MenuController extends Controller
{
    /**
     * Return filtered menu for the authenticated user (for mobile app).
     * Same filtering logic as ModifyMenuBasedOnRole: permissions + team modules.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $user->load(['currentTeam.modules', 'roles', 'teams']);

        $team = $user->currentTeam;
        if (! $team)
        {
            $team = $user->teams()->first();
            if ($team)
            {
                $user->forceFill(['current_team_id' => $team->id])->save();
            }
        }

        if ($team && ! $team->relationLoaded('modules'))
        {
            $team->load('modules');
        }

        $teamKey = $team?->id ?? 'none';
        $rolesFingerprint = md5($user->roles->pluck('name')->sort()->values()->implode(','));
        $cacheKey = "api_menu_user_{$user->id}_team_{$teamKey}_roles_{$rolesFingerprint}_".app()->getLocale().'_restricted_v4';

        $menu = Cache::remember($cacheKey, 3600, function () use ($user, $team)
        {
            $menuConfig = MenuHelper::filterMenuForUser(MenuHelper::getMenuConfig(), $user, $team);

            return array_values($menuConfig['menu']);
        });

        $items = $this->formatMenuForApi($menu);

        $enabledModules = $team
            ? $team->modules()
                ->where('module_team.status', 1)
                ->pluck('key')
                ->values()
                ->all()
            : [];

        return response()->json([
            'success' => true,
            'menu' => $items,
            'enabled_modules' => $enabledModules,
        ]);
    }

    /**
     * Format menu items for API: menu_header, name, url, slug, icon, module_key.
     * Extract Tabler icon class (e.g. "ti ti-layout-grid") from full icon string.
     */
    private function formatMenuForApi(array $menu): array
    {
        $result = [];

        foreach ($menu as $item)
        {
            if (isset($item['menuHeader']))
            {
                $header = trim((string) $item['menuHeader']);
                $result[] = [
                    'type' => 'header',
                    'menu_header' => $header !== '' ? __($header) : '',
                ];

                continue;
            }

            $icon = $item['icon'] ?? '';
            if (preg_match('/ti ti-[a-z0-9-]+/', $icon, $m))
            {
                $icon = $m[0];
            }

            $name = trim((string) ($item['name'] ?? ''));

            $result[] = [
                'type' => 'item',
                'name' => $name !== '' ? __($name) : '',
                'url' => $item['url'] ?? '',
                'slug' => $item['slug'] ?? '',
                'icon' => $icon,
                'module_key' => $item['module_key'] ?? null,
            ];
        }

        return $result;
    }
}
