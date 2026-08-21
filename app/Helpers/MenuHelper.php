<?php

namespace App\Helpers;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class MenuHelper
{
    /**
     * Modules that stay in the menu even when the team toggle is off.
     *
     * @var list<string>
     */
    private const ALWAYS_VISIBLE_MODULES = ['dashboard', 'settings'];

    public static function menuLabel(?string $key): string
    {
        if ($key === null || $key === '')
        {
            return '';
        }

        $translated = __($key);

        return is_string($translated) ? $translated : $key;
    }

    public static function getMenuConfig()
    {
        $menuType = env('APP_MENU_TYPE', 'vertical');
        $menuFile = resource_path("menu/{$menuType}Menu.json");

        if (! file_exists($menuFile))
        {
            $menuFile = resource_path('menu/verticalMenu.json');
        }

        return json_decode(file_get_contents($menuFile), true);
    }

    /**
     * Filter the sidebar menu by role permissions and enabled team modules.
     *
     * Collaborators keep Projects even when the team module is disabled: they
     * already have ProjectPolicy::viewAny and work against assigned projects.
     *
     * @param  array{menu: list<array<string, mixed>>}  $menuConfig
     * @return array{menu: list<array<string, mixed>>}
     */
    public static function filterMenuForUser(array $menuConfig, User $user, ?Team $team): array
    {
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

                continue;
            }

            $moduleKey = $menuItem['module_key'] ?? null;

            if (! $user->hasRole('admin'))
            {
                if (isset($menuItem['permission']) && ! $user->can($menuItem['permission']))
                {
                    continue;
                }
            }

            if ($moduleKey && ! in_array($moduleKey, self::ALWAYS_VISIBLE_MODULES, true))
            {
                if (! self::userCanSeeModuleMenuItem($user, $team, $moduleKey))
                {
                    continue;
                }
            }

            $sectionItems[] = $menuItem;
        }

        if ($currentSection && count($sectionItems) > 0)
        {
            $filteredMenu[] = $currentSection;
            $filteredMenu = array_merge($filteredMenu, $sectionItems);
        }

        $menuConfig['menu'] = array_values($filteredMenu);

        return $menuConfig;
    }

    public static function userCanSeeModuleMenuItem(User $user, ?Team $team, string $moduleKey): bool
    {
        if ($team && $team->hasModule($moduleKey))
        {
            return true;
        }

        if ($moduleKey === 'projects' && $user->hasRole('collaborator') && $user->can('viewAny', Project::class))
        {
            return true;
        }

        return false;
    }

    /**
     * Resolve CSS active class for a vertical/horizontal menu item.
     *
     * Matches the Vuexy convention: exact slug or route-name prefix
     * (e.g. slug "task" → task.show). Also supports active_query for
     * items that share a route and differ by query string.
     */
    public static function menuActiveClass(object $menu, ?string $currentRouteName = null, bool $withSubmenuOpen = false): ?string
    {
        $currentRouteName ??= Route::currentRouteName();
        $active = $withSubmenuOpen && isset($menu->submenu) ? 'active open' : 'active';

        if (! self::routeMatchesSlugs($currentRouteName, $menu->slug ?? null))
        {
            return null;
        }

        if (isset($menu->active_query))
        {
            foreach ((array) $menu->active_query as $queryKey => $queryValue)
            {
                if ((string) request()->query($queryKey) !== (string) $queryValue)
                {
                    return null;
                }
            }
        }

        return $active;
    }

    /**
     * @param  string|array<int, string>|null  $slug
     */
    public static function routeMatchesSlugs(?string $currentRouteName, string|array|null $slug): bool
    {
        if ($currentRouteName === null || $currentRouteName === '')
        {
            return false;
        }

        $slugs = is_array($slug) ? $slug : [$slug];

        foreach ($slugs as $item)
        {
            if (! is_string($item) || $item === '')
            {
                continue;
            }

            if ($currentRouteName === $item)
            {
                return true;
            }

            // Prefix match: "task" → task.show ; "funnel" → funnel.create / funnel-list
            if (str_starts_with($currentRouteName, $item) && (
                strlen($currentRouteName) === strlen($item)
                || in_array($currentRouteName[strlen($item)] ?? '', ['.', '-'], true)
            ))
            {
                return true;
            }
        }

        return false;
    }
}
