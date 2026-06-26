<?php

namespace App\Services\ControlPanel;

use App\Contracts\ControlPanelConnector;
use App\Models\Domain;
use App\Models\Server;

class PleskConnector implements ControlPanelConnector
{
    public function supports(Server $server): bool
    {
        return $server->control_panel === 'plesk';
    }

    public function testConnection(Server $server): array
    {
        return $this->notImplemented();
    }

    public function listAccounts(Server $server): array
    {
        return $this->notImplemented();
    }

    public function syncAccounts(Server $server): array
    {
        return $this->notImplemented();
    }

    public function listPlans(Server $server): array
    {
        return $this->notImplemented();
    }

    public function changePlan(Server $server, Domain $domain, string $plan): array
    {
        return $this->notImplemented();
    }

    public function listEmailAccounts(Server $server, Domain $domain): array
    {
        return $this->notImplemented();
    }

    public function changeEmailPassword(Server $server, Domain $domain, string $email, string $password): array
    {
        return $this->notImplemented();
    }

    public function getMxRecords(Server $server, Domain $domain): array
    {
        return $this->notImplemented();
    }

    public function updateMxRecords(Server $server, Domain $domain, array $records): array
    {
        return $this->notImplemented();
    }

    /**
     * @return array{success: false, error: string}
     */
    private function notImplemented(): array
    {
        return [
            'success' => false,
            'error' => 'Plesk integration is not available yet. Server saved for future use.',
        ];
    }
}
