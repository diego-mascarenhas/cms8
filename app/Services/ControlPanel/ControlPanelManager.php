<?php

namespace App\Services\ControlPanel;

use App\Contracts\ControlPanelConnector;
use App\Models\Server;
use InvalidArgumentException;

class ControlPanelManager
{
    public function __construct(
        private CpanelConnector $cpanelConnector,
        private PleskConnector $pleskConnector,
    ) {}

    public function forServer(Server $server): ControlPanelConnector
    {
        return match ($server->control_panel)
        {
            'cpanel' => $this->cpanelConnector,
            'plesk' => $this->pleskConnector,
            default => throw new InvalidArgumentException('Server has no supported control panel configured.'),
        };
    }

    public function supports(Server $server): bool
    {
        return in_array($server->control_panel, ['cpanel', 'plesk'], true);
    }
}
