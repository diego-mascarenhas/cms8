<?php

// app/Http/Middleware/ModifyMenuBasedOnRole.php
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

        if ($user) {
            // Filtrar el menú según el rol del usuario
            if (!$user->hasRole('admin')) {
                // Filtrar elementos específicos del menú que son solo para administradores
                $adminOnlySlugs = [
                    'laravel-example-user-management',
                    'app-client-list',
                    'app-service-list',
                    'app-project-list',
                    'app-invoice-list',
                    'app-payment-list',
                    'app-communication-list'
                ];

                // Filtrar elementos específicos del menú que son solo para administradores
                $verticalMenuData->menu = array_filter($verticalMenuData->menu, function ($menuItem) use ($adminOnlySlugs) {
                    // Si el item no tiene 'slug' y es un encabezado, filtrarlo si está en la sección de 'Admin'
                    if (isset($menuItem->menuHeader) && $menuItem->menuHeader === 'Admin') {
                        return false;
                    }

                    // Verificar si el item tiene un 'slug' antes de aplicar el filtro
                    if (isset($menuItem->slug)) {
                        return !in_array($menuItem->slug, $adminOnlySlugs);
                    }

                    // Mantener el item si no tiene 'slug' y no es un encabezado de 'Admin'
                    return true;
                });

                // Reindexar el array para evitar problemas en JavaScript
                $verticalMenuData->menu = array_values($verticalMenuData->menu);
            }

            \View::share('menuData', [$verticalMenuData, $horizontalMenuData]);
        }

        return $next($request);
    }
}
