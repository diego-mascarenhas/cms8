<?php

namespace App\Services\Fiscal\Cuentica;

use App\Models\Team;

class CuenticaClientFactory
{
    /**
     * Resolve the Cuéntica token for a team.
     *
     * Each team (including Humano's own) exports its invoices to its own
     * Cuéntica account, so the token MUST be configured per team in team
     * settings. There is intentionally no global/.env fallback: a team without
     * a token exports nothing.
     */
    public function tokenForTeam(Team $team): ?string
    {
        $teamToken = trim((string) $team->getSetting('cuentica_api_token', ''));

        return $teamToken !== '' ? $teamToken : null;
    }

    public function forTeam(Team $team): ?CuenticaApiClient
    {
        $token = $this->tokenForTeam($team);
        if ($token === null)
        {
            return null;
        }

        return new CuenticaApiClient(
            token: $token,
            baseUrl: (string) config('fiscal.platforms.cuentica.base_url', 'https://api.cuentica.com'),
            timeout: (int) config('fiscal.platforms.cuentica.timeout', 30),
        );
    }
}
