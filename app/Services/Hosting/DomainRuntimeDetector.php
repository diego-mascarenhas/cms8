<?php

namespace App\Services\Hosting;

use App\Models\Domain;
use App\Services\ControlPanel\ControlPanelManager;

class DomainRuntimeDetector
{
    public function __construct(private readonly ControlPanelManager $controlPanelManager) {}

    public function fillMissing(Domain $domain, bool $force = false): void
    {
        $domain->loadMissing('server');

        if (filled($domain->site_type) && filled($domain->php_version))
        {
            return;
        }

        $server = $domain->server;

        if ($server === null || ! $server->hasToken() || ! $this->controlPanelManager->supports($server))
        {
            return;
        }

        $probedAt = data_get($domain->data, 'runtime_probed_at');

        if (! $force && is_string($probedAt) && now()->parse($probedAt)->greaterThan(now()->subHours(6)))
        {
            return;
        }

        $connector = $this->controlPanelManager->forServer($server);

        if (blank($domain->php_version))
        {
            $version = $connector->phpVersion($server, $domain)['version'] ?? null;

            if (is_string($version) && $version !== '')
            {
                $domain->php_version = $version;
            }
        }

        if (blank($domain->site_type))
        {
            $type = $connector->siteType($server, $domain)['site_type'] ?? null;

            if (! is_string($type) || $type === '')
            {
                $type = $domain->isWordPress() ? 'WordPress' : null;
            }

            if (is_string($type) && $type !== '')
            {
                $domain->site_type = $type;
            }
        }

        $data = is_array($domain->data) ? $domain->data : [];
        $data['runtime_probed_at'] = now()->toIso8601String();
        $domain->data = $data;
        $domain->save();
    }
}
