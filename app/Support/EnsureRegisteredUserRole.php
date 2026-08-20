<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Give a freshly created account a Spatie role.
 *
 * API registration builds the personal team itself and does not go through Fortify,
 * so this must run after that team exists: otherwise the owner is left with no role
 * and ContactPolicy / inbox actions return "This action is unauthorized."
 */
class EnsureRegisteredUserRole
{
    public static function assignIfMissing(User $user): void
    {
        if ($user->roles()->exists())
        {
            return;
        }

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');

        if ($user->ownedTeams()->where('personal_team', true)->exists())
        {
            $user->assignRole('admin');

            return;
        }

        $user->assignRole('user');
    }
}
