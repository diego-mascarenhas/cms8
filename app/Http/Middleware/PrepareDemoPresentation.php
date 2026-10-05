<?php

namespace App\Http\Middleware;

use App\Support\DemoTeam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrepareDemoPresentation
{
    public function handle(Request $request, Closure $next): Response
    {
        $team = $request->user()?->currentTeam;

        if (! DemoTeam::isDemoTeam($team))
        {
            return $next($request);
        }

        config(['telescope.enabled' => false]);

        return $next($request);
    }
}
