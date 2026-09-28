<?php

namespace App\Support;

use App\Models\User;

class AffiliateDirectoryAccess
{
    public static function allows(?User $user): bool
    {
        if ($user === null || ! $user->canAccessBilling())
        {
            return false;
        }

        if ($user->hasRole('root'))
        {
            return true;
        }

        $team = $user->currentTeam;

        return $team !== null && AffiliateCommission::isPlatformTeam($team);
    }
}
