<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Module;
use App\Helpers\MenuHelper;

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
        $menuConfig = MenuHelper::getMenuConfig();
        $horizontalMenuJson = file_get_contents(base_path('resources/menu/horizontalMenu.json'));
        $horizontalMenuData = json_decode($horizontalMenuJson);

        $user = Auth::user();

        if ($user)
        {
            // Get the current team
            $team = $user->currentTeam;
            
            // Get all core modules
            $coreModules = Module::where('is_core', true)->pluck('key')->toArray();
            
            // Filter the menu based on the user's permissions and team's modules
            $filteredMenu = [];
            $currentSection = null;
            $sectionItems = [];

            foreach ($menuConfig['menu'] as $menuItem)
            {
                if (isset($menuItem['menuHeader']))
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
                else
                {
                    // Check if the menu item has a module key
                    $moduleKey = $menuItem['module_key'] ?? null;
                    
                    // Skip if the user doesn't have permission
                    if (isset($menuItem['permission']) && !$user->can($menuItem['permission'])) {
                        continue;
                    }
                    
                    // If it's a core module, always show it
                    // If it's not a core module, check if the team has access
                    if ($moduleKey && !in_array($moduleKey, $coreModules) && !$team->hasModule($moduleKey)) {
                        continue;
                    }
                    
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
            $menuConfig['menu'] = array_values($filteredMenu);

            \View::share('menuData', [json_decode(json_encode($menuConfig)), $horizontalMenuData]);
        }

        return $next($request);
    }
}