<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Route;

class MenuHelper
{
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
