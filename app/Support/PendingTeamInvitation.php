<?php

namespace App\Support;

use App\Models\TeamInvitation;
use Illuminate\Http\Request;

class PendingTeamInvitation
{
    public const SESSION_KEY = 'pending_team_invitation_id';

    public static function store(Request $request, TeamInvitation $invitation): void
    {
        $request->session()->put(self::SESSION_KEY, $invitation->id);
    }

    public static function get(Request $request): ?TeamInvitation
    {
        $id = $request->session()->get(self::SESSION_KEY);

        if (! $id)
        {
            return null;
        }

        return TeamInvitation::query()->with('team')->find($id);
    }

    public static function pull(Request $request): ?TeamInvitation
    {
        $invitation = self::get($request);

        if ($invitation)
        {
            $request->session()->forget(self::SESSION_KEY);
        }

        return $invitation;
    }
}
