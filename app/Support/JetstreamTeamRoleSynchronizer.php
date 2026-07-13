<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

class JetstreamTeamRoleSynchronizer
{
    /**
     * Jetstream team role key => Spatie role name.
     *
     * @var array<string, string>
     */
    public const ROLE_MAP = [
        'admin' => 'admin',
        'editor' => 'editor',
        'collaborator' => 'collaborator',
        'developer' => 'developer',
        'technical' => 'technical',
        'employee' => 'employee',
        'client' => 'client',
        'auditor' => 'auditor',
        'guest' => 'guest',
        'user' => 'user',
    ];

    /**
     * Replace mapped Spatie roles so they match the Jetstream team role.
     */
    public function sync(User $user, ?string $jetstreamRole): void
    {
        if ($jetstreamRole === null || $jetstreamRole === '')
        {
            return;
        }

        if (! isset(self::ROLE_MAP[$jetstreamRole]))
        {
            return;
        }

        $targetSpatieRole = self::ROLE_MAP[$jetstreamRole];

        foreach (array_unique(array_values(self::ROLE_MAP)) as $spatieRole)
        {
            if ($spatieRole === $targetSpatieRole)
            {
                continue;
            }

            try
            {
                if ($user->hasRole($spatieRole))
                {
                    $user->removeRole($spatieRole);
                }
            } catch (\Throwable)
            {
            }
        }

        if ($user->hasRole($targetSpatieRole))
        {
            return;
        }

        if (Role::query()->where('name', $targetSpatieRole)->where('guard_name', 'web')->doesntExist())
        {
            return;
        }

        try
        {
            $user->assignRole($targetSpatieRole);
        } catch (\Throwable)
        {
        }
    }

    /**
     * Reconcile Spatie roles after a team membership is removed or becomes stale.
     */
    public function syncFromRemainingMemberships(User $user, ?\App\Models\Team $removedFromTeam = null): void
    {
        $user->loadMissing(['teams', 'ownedTeams']);

        if ($removedFromTeam !== null && (int) $user->current_team_id === (int) $removedFromTeam->id)
        {
            $fallbackTeam = $user->teams()->first() ?? $user->ownedTeams()->first();

            $user->forceFill(['current_team_id' => $fallbackTeam?->id])->save();
            $user->refresh();
            $user->loadMissing(['teams', 'ownedTeams']);
        }

        $currentTeam = $user->currentTeam;

        if ($currentTeam !== null && $user->ownsTeam($currentTeam))
        {
            $this->sync($user, 'admin');

            return;
        }

        if ($currentTeam !== null)
        {
            $membership = $user->teams->firstWhere('id', $currentTeam->id);

            if ($membership?->pivot?->role)
            {
                $this->sync($user, (string) $membership->pivot->role);

                return;
            }
        }

        $firstMembership = $user->teams->first();

        if ($firstMembership?->pivot?->role)
        {
            if ((int) $user->current_team_id !== (int) $firstMembership->id)
            {
                $user->forceFill(['current_team_id' => $firstMembership->id])->save();
                $user->refresh();
            }

            $this->sync($user, (string) $firstMembership->pivot->role);

            return;
        }

        $linkedContact = $user->contact()->withoutGlobalScopes()->first();

        if ($linkedContact !== null)
        {
            $this->sync($user, 'client');

            return;
        }

        $this->clearMappedRoles($user);
    }

    /**
     * @param  array<int, string>  $except
     */
    public function clearMappedRoles(User $user, array $except = []): void
    {
        foreach (array_unique(array_values(self::ROLE_MAP)) as $spatieRole)
        {
            if (in_array($spatieRole, $except, true))
            {
                continue;
            }

            try
            {
                if ($user->hasRole($spatieRole))
                {
                    $user->removeRole($spatieRole);
                }
            } catch (\Throwable)
            {
            }
        }
    }
}
