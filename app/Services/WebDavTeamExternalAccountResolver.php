<?php

namespace App\Services;

use App\Enums\ExternalProvider;
use App\Models\ExternalAccount;

class WebDavTeamExternalAccountResolver
{
    public function firstWebDavAccountForTeam(int $teamId): ?ExternalAccount
    {
        return ExternalAccount::query()
            ->where('team_id', $teamId)
            ->where('provider', ExternalProvider::WebDav)
            ->orderBy('id')
            ->first();
    }
}
