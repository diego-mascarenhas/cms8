<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate API requests with either:
 * - Team API tokens from Team Settings → API Tokens (/team/{id}/api-tokens)
 * - Laravel Sanctum personal access tokens (existing SPAs / mobile)
 */
class AuthenticateSanctumOrTeamToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->authenticateViaTeamToken($request))
        {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();

        if ($user)
        {
            Auth::setUser($user);
            $request->setUserResolver(static fn () => $user);

            return $next($request);
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    protected function authenticateViaTeamToken(Request $request): bool
    {
        $bearer = $request->bearerToken();

        if (! $bearer)
        {
            return false;
        }

        $tokenHash = hash('sha256', $bearer);

        $team = Team::query()
            ->whereHas('settings', function ($query)
            {
                $query->whereIn('key', ['api_token_hash', 'api_tokens']);
            })
            ->with(['settings', 'owner.roles'])
            ->get()
            ->first(function (Team $team) use ($tokenHash)
            {
                return $team->findApiTokenByHash($tokenHash) !== null;
            });

        if (! $team)
        {
            return false;
        }

        $user = $team->owner;

        if (! $user instanceof User)
        {
            $user = User::with('roles')->find($team->user_id);
        }

        if (! $user instanceof User)
        {
            return false;
        }

        if (! $user->relationLoaded('roles'))
        {
            $user->load('roles');
        }

        // Scope the request to this team without persisting current_team_id.
        $user->current_team_id = $team->id;
        $user->setRelation('currentTeam', $team);

        Auth::guard('sanctum')->setUser($user);
        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('team', $team);
        $request->attributes->set('team_api_token', $team->findApiTokenByHash($tokenHash));
        $request->attributes->set('auth_via', 'team_token');
        $request->merge(['team_id' => $team->id]);

        return true;
    }
}
