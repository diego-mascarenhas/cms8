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
            // Filter the menu based on the user's role
            if (!$user->hasRole('admin'))
            {
                // Filter specific menu items that are admin-only
                $adminOnlySlugs = [
                    'user-management',
                    'app-client-list',
                    'app-service-list',
                    'app-project-list',
                    'app-invoice-list',
                    'app-payment-list',
                    'app-communication-list'
                ];

                // Filter specific menu items that are admin-only
                $verticalMenuData->menu = array_filter($verticalMenuData->menu, function ($menuItem) use ($adminOnlySlugs)
                {
                    // If the item has no 'slug' and is a header, filter it if it's in the 'Admin' section
                    if (isset($menuItem->menuHeader) && $menuItem->menuHeader === 'Admin')
                    {
                        return false;
                    }

                    // Check if the item has a 'slug' before applying the filter
                    if (isset($menuItem->slug))
                    {
                        return !in_array($menuItem->slug, $adminOnlySlugs);
                    }

                    // Keep the item if it has no 'slug' and is not an 'Admin' header
                    return true;
                });

                // Reindex the array to avoid issues in JavaScript
                $verticalMenuData->menu = array_values($verticalMenuData->menu);
            }

            \View::share('menuData', [$verticalMenuData, $horizontalMenuData]);
        }

        return $next($request);
    }
}