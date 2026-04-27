<?php

namespace App\Services;

use App\Enums\ExternalProvider;
use App\Models\ExternalAccount;

class GoogleTeamExternalAccountResolver
{
    /**
     * First Google OAuth account linked to the team (stable ordering by id).
     */
    public function firstGoogleAccountForTeam(int $teamId): ?ExternalAccount
    {
        return ExternalAccount::query()
            ->where('team_id', $teamId)
            ->where('provider', ExternalProvider::Google)
            ->orderBy('id')
            ->first();
    }
}
