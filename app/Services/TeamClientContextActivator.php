<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;

class TeamClientContextActivator
{
    /**
     * @var list<string>
     */
    private const STAFF_TEAM_ROLES = [
        'admin',
        'editor',
        'collaborator',
        'technical',
        'developer',
    ];

    /**
     * Switch the user onto the provider team. Staff keep their membership;
     * everyone else becomes a client of that team (B2B2C). Spatie roles on
     * other workspaces are left untouched.
     */
    public function activate(User $user, int $teamId): void
    {
        $team = Team::query()->find($teamId);
        if (! $team instanceof Team)
        {
            return;
        }

        if ($this->isProviderStaff($user, $team))
        {
            $this->switchToTeam($user, $team);

            return;
        }

        if (! $user->belongsToTeam($team))
        {
            $user->teams()->attach($team->id, ['role' => 'client']);
        } elseif (! $user->hasTeamRole($team, 'client'))
        {
            $user->teams()->updateExistingPivot($team->id, ['role' => 'client']);
        }

        $user->unsetRelation('teams');
        $this->switchToTeam($user, $team);
    }

    private function isProviderStaff(User $user, Team $team): bool
    {
        if ($user->ownsTeam($team))
        {
            return true;
        }

        foreach (self::STAFF_TEAM_ROLES as $role)
        {
            if ($user->hasTeamRole($team, $role))
            {
                return true;
            }
        }

        return false;
    }

    private function switchToTeam(User $user, Team $team): void
    {
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->unsetRelation('currentTeam');
        $user->setRelation('currentTeam', $team);
    }
}
