<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class AssignableTeamUsers
{
    /**
     * Staff-like team membership roles that can be assigned as project/task responsible.
     * Matches Jetstream pivot roles (team_user.role), not global Spatie roles.
     * Spatie roles are global (permission.teams=false), so a B2B2C client from another
     * workspace may still hold Spatie "admin" while their pivot role here is "client".
     *
     * @var list<string>
     */
    public const ROLES = [
        'root',
        'admin',
        'collaborator',
        'editor',
        'marketing',
        'developer',
        'technical',
        'employee',
    ];

    /**
     * Team members that can be assigned work (excludes client portal users).
     *
     * @return Collection<int, User>
     */
    public static function forTeam(Team $team): Collection
    {
        $ownerId = (int) $team->user_id;

        return $team->allUsers()
            ->filter(function (User $teamUser) use ($ownerId)
            {
                if ($ownerId > 0 && (int) $teamUser->id === $ownerId)
                {
                    return true;
                }

                $pivotRole = $teamUser->membership->role ?? null;

                return is_string($pivotRole) && in_array($pivotRole, self::ROLES, true);
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return Collection<int|string, string> id => name
     */
    public static function optionsForTeam(Team $team): Collection
    {
        return self::forTeam($team)->pluck('name', 'id');
    }
}
