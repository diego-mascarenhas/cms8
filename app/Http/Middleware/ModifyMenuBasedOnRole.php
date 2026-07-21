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

            // Resolve team safely; if user has no current team, switch to first available.
            // Important: forceFill alone leaves a cached null currentTeam relation and causes
            // 500s on the same request (e.g. autologin → dashboard.collaborator).
            $team = $user->currentTeam;
            if (! $team)
            {
                $team = $user->teams->first() ?? $user->ownedTeams()->first();
                if ($team)
                {
                    $user->switchTeam($team);
                }
            }

            if (! $team && ! $this->allowsRequestsWithoutTeam($request))
            {
                return redirect()->route('error-without-team');
            }

            // Skip menu processing for AJAX/Livewire requests (performance optimization)
            if ($request->ajax() || $request->header('X-Livewire'))
            {
                return $next($request);
            }

            // Eager load modules to avoid N+1 queries when checking hasModule()
            if ($team && ! $team->relationLoaded('modules'))
            {
                $team->load('modules');
            }

            // Cache menu for 1 hour per user/team combination.
            // Include menu file mtimes and enabled team modules so module toggles bust the cache.
            $teamKey = $team?->id ?? 'none';
            $menuVersion = (int) max(
                @filemtime(base_path('resources/menu/verticalMenu.json')) ?: 0,
                @filemtime(base_path('resources/menu/horizontalMenu.json')) ?: 0,
            );
            $enabledModuleKeys = $team
                ? $team->modules
                    ->where('pivot.status', 1)
                    ->pluck('key')
                    ->filter()
                    ->sort()
                    ->values()
                    ->implode(',')
                : '';
            $modulesFingerprint = md5($enabledModuleKeys);
            $rolesFingerprint = md5($user->roles->pluck('name')->sort()->values()->implode(','));
            $cacheKey = "menu_user_{$user->id}_team_{$teamKey}_roles_{$rolesFingerprint}_mods_{$modulesFingerprint}_v{$menuVersion}_restricted_v3";
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

    /**
     * Routes that must remain reachable while the user has no resolvable team.
     */
    private function allowsRequestsWithoutTeam(Request $request): bool
    {
        if (str_starts_with($request->path(), 'livewire'))
        {
            return true;
        }

        return $request->routeIs([
            'error-without-team',
            'logout',
            'teams.create',
            'teams.invitations.confirm',
        ]);
    }
}
