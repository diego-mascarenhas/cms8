<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModifyMenuBasedOnRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $verticalMenuJson = file_get_contents(base_path('resources/menu/verticalMenu.json'));
        $verticalMenuData = json_decode($verticalMenuJson);
        $horizontalMenuJson = file_get_contents(base_path('resources/menu/horizontalMenu.json'));
        $horizontalMenuData = json_decode($horizontalMenuJson);

        $user = Auth::user();

        if ($user)
        {
            // Filter the menu based on the user's permissions
            $filteredMenu = [];
            $currentSection = null;
            $sectionItems = [];

            foreach ($verticalMenuData->menu as $menuItem)
            {
                if (isset($menuItem->menuHeader))
                {
                    // If we are starting a new section, add the previous section if it had items
                    if ($currentSection && count($sectionItems) > 0)
                    {
                        $filteredMenu[] = $currentSection;
                        $filteredMenu = array_merge($filteredMenu, $sectionItems);
                    }
                    // Start a new section
                    $currentSection = $menuItem;
                    $sectionItems = [];
                }
                elseif (isset($menuItem->permission) && !$user->can($menuItem->permission))
                {
                    // If the user does not have the permission, skip this item
                    continue;
                }
                else
                {
                    // Add the item to the current section
                    $sectionItems[] = $menuItem;
                }
            }

            // Add the last section if it had items
            if ($currentSection && count($sectionItems) > 0)
            {
                $filteredMenu[] = $currentSection;
                $filteredMenu = array_merge($filteredMenu, $sectionItems);
            }

            // Reindex the array to avoid issues in JavaScript
            $verticalMenuData->menu = array_values($filteredMenu);

            \View::share('menuData', [$verticalMenuData, $horizontalMenuData]);
        }

        return $next($request);
    }
}