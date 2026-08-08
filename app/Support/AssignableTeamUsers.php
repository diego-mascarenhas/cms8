<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class AssignableTeamUsers
{
    /**
     * Staff-like Spatie roles that can be assigned as project/task responsible.
     * Matches the IDONEO Projects SPA filter (?assignable=1).
     *
     * @var list<string>
     */
    public const ROLES = [
        'root',
        'admin',
        'collaborator',
        'editor',
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
            ->load('roles')
            ->filter(function (User $teamUser) use ($ownerId)
            {
                if ($ownerId > 0 && (int) $teamUser->id === $ownerId)
                {
                    return true;
                }

                return $teamUser->hasAnyRole(self::ROLES);
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
