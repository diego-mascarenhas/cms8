<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        if (!Auth::check())
        {
            abort(403, 'Unauthorized action.');
        }

        foreach ($roles as $role)
        {
            if (Auth::user()->hasRole($role))
            {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized action.');
    }
}

