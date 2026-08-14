<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeamTokenAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token)
        {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        $tokenHash = hash('sha256', $token);

        $team = Team::whereHas('settings', function ($query)
        {
            $query->whereIn('key', ['api_token_hash', 'api_tokens']);
        })->with('settings')->get()->first(function (Team $team) use ($tokenHash)
        {
            return $team->findApiTokenByHash($tokenHash) !== null;
        });

        if (! $team)
        {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $request->merge(['team_id' => $team->id]);
        $request->attributes->set('team', $team);
        $request->attributes->set('team_api_token', $team->findApiTokenByHash($tokenHash));

        return $next($request);
    }
}
