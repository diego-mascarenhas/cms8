<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordsUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            abort(403);
        }

        $flagKey = "passwords_unlocked_team_{$team->id}";
        $untilKey = "passwords_unlocked_until_team_{$team->id}";
        $isUnlocked = (bool) $request->session()->get($flagKey, false);
        $unlockedUntil = (int) $request->session()->get($untilKey, 0);

        if (! $isUnlocked || $unlockedUntil < now()->timestamp)
        {
            $request->session()->forget($flagKey);
            $request->session()->forget($untilKey);

            return redirect()
                ->route('passwords.unlock.form')
                ->with('error', __('Unlock your vault to continue.'));
        }

        return $next($request);
    }
}
