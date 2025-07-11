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

        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        $tokenHash = hash('sha256', $token);
        
        // Find team with this token
        $team = Team::whereHas('settings', function ($query) use ($tokenHash) {
            $query->where('key', 'api_token_hash');
        })->with('settings')->get()->first(function ($team) use ($tokenHash) {
            $tokenSetting = $team->settings->where('key', 'api_token_hash')->first();
            return $tokenSetting && $tokenSetting->value === $tokenHash;
        });

        if (!$team) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        // Add team to request
        $request->merge(['team_id' => $team->id]);
        $request->attributes->set('team', $team);

        return $next($request);
    }
} 