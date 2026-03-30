<?php

namespace App\Http\Middleware;

use App\Helpers\MenuHelper;
use App\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

class ModifyMenuBasedOnRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user)
        {
            // Skip menu processing for AJAX/Livewire requests (performance optimization)
            if ($request->ajax() || $request->header('X-Livewire'))
            {
                return $next($request);
            }

            // Eager load user relationships to avoid N+1 queries
            $relationsToLoad = [];

            if (! $user->relationLoaded('currentTeam'))
            {
                $relationsToLoad[] = 'currentTeam.modules';
                $relationsToLoad[] = 'currentTeam.settings';
            }

            if (! $user->relationLoaded('roles'))
            {
                $relationsToLoad[] = 'roles';
            }

            if (! $user->relationLoaded('teams'))
            {
                $relationsToLoad[] = 'teams';
            }

            if (! empty($relationsToLoad))
            {
                $user->load($relationsToLoad);
            }

            // Resolve team safely; if user has no current team, try first attached team
            $team = $user->currentTeam;
            if (! $team)
            {
                $team = $user->teams()->first();
                if ($team && (int) ($user->current_team_id ?? 0) !== (int) $team->id)
                {
                    $user->forceFill(['current_team_id' => $team->id])->save();
                }
            }

            // Eager load modules to avoid N+1 queries when checking hasModule()
            if ($team && ! $team->relationLoaded('modules'))
            {
                $team->load('modules');
            }

            // Cache menu for 1 hour per user/team combination
            $teamKey = $team?->id ?? 'none';
            $cacheKey = "menu_user_{$user->id}_team_{$teamKey}";
            $menuData = Cache::remember($cacheKey, 3600, function () use ($user, $team)
            {
                $menuConfig = MenuHelper::getMenuConfig();
                $horizontalMenuJson = file_get_contents(base_path('resources/menu/horizontalMenu.json'));
                $horizontalMenuData = json_decode($horizontalMenuJson);

                // Team resolved above (may be null)

                // Get all core modules
                $coreModules = Module::where('is_core', true)->pluck('key')->toArray();

                // Modules that should always be visible regardless of team settings
                $alwaysVisibleModules = ['dashboard', 'settings'];

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
                    } else
                    {
                        // Check if the menu item has a module key
                        $moduleKey = $menuItem['module_key'] ?? null;

                        // Skip permission check if user has admin role (admins have access to everything)
                        if (! $user->hasRole('admin'))
                        {
                            // For non-admin users, skip if they don't have the required permission
                            if (isset($menuItem['permission']) && ! $user->can($menuItem['permission']))
                            {
                                continue;
                            }
                        }

                        // Gate all menu items by team module setting, except a small allowlist
                        if ($moduleKey && ! in_array($moduleKey, $alwaysVisibleModules))
                        {
                            if (! $team || ! $team->hasModule($moduleKey))
                            {
                                continue;
                            }
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

                return [json_decode(json_encode($menuConfig)), $horizontalMenuData];
            });

            View::share('menuData', $menuData);
        }

        return $next($request);
    }
}
