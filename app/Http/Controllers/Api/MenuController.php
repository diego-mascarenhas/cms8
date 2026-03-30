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
        $cacheKey = "api_menu_user_{$user->id}_team_{$teamKey}";

        $menu = Cache::remember($cacheKey, 3600, function () use ($user, $team)
        {
            $menuConfig = MenuHelper::getMenuConfig();
            $alwaysVisibleModules = ['dashboard', 'settings'];
            $filteredMenu = [];
            $currentSection = null;
            $sectionItems = [];

            foreach ($menuConfig['menu'] as $menuItem)
            {
                if (isset($menuItem['menuHeader']))
                {
                    if ($currentSection && count($sectionItems) > 0)
                    {
                        $filteredMenu[] = $currentSection;
                        $filteredMenu = array_merge($filteredMenu, $sectionItems);
                    }
                    $currentSection = $menuItem;
                    $sectionItems = [];
                } else
                {
                    $moduleKey = $menuItem['module_key'] ?? null;

                    if (! $user->hasRole('admin'))
                    {
                        if (isset($menuItem['permission']) && ! $user->can($menuItem['permission']))
                        {
                            continue;
                        }
                    }

                    if ($moduleKey && ! in_array($moduleKey, $alwaysVisibleModules))
                    {
                        if (! $team || ! $team->hasModule($moduleKey))
                        {
                            continue;
                        }
                    }

                    $sectionItems[] = $menuItem;
                }
            }

            if ($currentSection && count($sectionItems) > 0)
            {
                $filteredMenu[] = $currentSection;
                $filteredMenu = array_merge($filteredMenu, $sectionItems);
            }

            return array_values($filteredMenu);
        });

        $items = $this->formatMenuForApi($menu);

        return response()->json([
            'success' => true,
            'menu' => $items,
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
                $result[] = [
                    'type' => 'header',
                    'menu_header' => $item['menuHeader'],
                ];

                continue;
            }

            $icon = $item['icon'] ?? '';
            if (preg_match('/ti ti-[a-z0-9-]+/', $icon, $m))
            {
                $icon = $m[0];
            }

            $result[] = [
                'type' => 'item',
                'name' => $item['name'] ?? '',
                'url' => $item['url'] ?? '',
                'slug' => $item['slug'] ?? '',
                'icon' => $icon,
                'module_key' => $item['module_key'] ?? null,
            ];
        }

        return $result;
    }
}
