<?php

namespace App\Services\Hosting;

use App\Models\Domain;
use App\Services\ControlPanel\ControlPanelManager;

class HostingPlanCatalog
{
    public function __construct(private readonly ControlPanelManager $controlPanelManager) {}

    public function isFlex(?string $plan): bool
    {
        $name = strtolower(trim((string) $plan));

        return $name === 'flex' || $name === strtolower((string) config('humano_hosting.flex_package', 'revision_flex'));
    }

    /**
     * @return list<array{name: string, quota_mb: int|null, unlimited: bool, current: bool, allowed: bool, reason: string|null}>
     */
    public function options(Domain $domain): array
    {
        if ($this->isFlex($domain->plan))
        {
            return [];
        }

        $packages = $this->packages($domain);
        $used = $this->usedMb($domain);
        $current = $this->find($packages, (string) $domain->plan);

        return array_map(function (array $package) use ($domain, $used, $current): array
        {
            $reason = $this->blockReason($used, $current, $package, (string) $domain->plan);

            return [
                'name' => $package['name'],
                'quota_mb' => $package['quota_mb'],
                'unlimited' => $package['unlimited'],
                'current' => strcasecmp($package['name'], (string) $domain->plan) === 0,
                'allowed' => $reason === null,
                'reason' => $reason,
            ];
        }, $packages);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function change(Domain $domain, string $plan): array
    {
        if ($this->isFlex($domain->plan))
        {
            return [
                'success' => false,
                'message' => 'Flex no cambia de plan.',
            ];
        }

        $chosen = $this->find($this->options($domain), $plan);

        if ($chosen === null)
        {
            return [
                'success' => false,
                'message' => 'Ese plan no está en este servidor.',
            ];
        }

        if ($chosen['current'])
        {
            return [
                'success' => false,
                'message' => 'El dominio ya está en ese plan.',
            ];
        }

        if (! $chosen['allowed'])
        {
            return [
                'success' => false,
                'message' => $chosen['reason'] ?? 'No se puede bajar a ese plan.',
            ];
        }

        $domain->loadMissing('server');
        $server = $domain->server;

        if ($server === null || ! $this->controlPanelManager->supports($server))
        {
            return [
                'success' => false,
                'message' => 'Para cambiar el plan se requiere un servidor con panel de control.',
            ];
        }

        $result = $this->controlPanelManager->forServer($server)->changePlan($server, $domain, $plan);

        if (! ($result['success'] ?? false))
        {
            return [
                'success' => false,
                'message' => (string) ($result['error'] ?? 'No se pudo actualizar el plan.'),
            ];
        }

        $domain->plan = $plan;
        $this->rememberDiskLimit($domain, $chosen);
        $domain->save();

        return [
            'success' => true,
            'message' => 'El plan quedó actualizado.',
        ];
    }

    /**
     * @param  array{quota_mb: int|null, unlimited: bool}  $package
     */
    private function rememberDiskLimit(Domain $domain, array $package): void
    {
        $data = is_array($domain->data) ? $domain->data : [];
        $disk = is_array($data['account_disk'] ?? null) ? $data['account_disk'] : [];
        $disk['unlimited'] = $package['unlimited'];
        $disk['limit_mb'] = $package['unlimited'] ? null : $package['quota_mb'];

        if (! $package['unlimited'] && $package['quota_mb'] && isset($disk['used_mb']) && is_numeric($disk['used_mb']))
        {
            $disk['usage_percent'] = (int) min(100, round(((float) $disk['used_mb'] / $package['quota_mb']) * 100));
        }

        $data['account_disk'] = $disk;
        $domain->data = $data;
    }

    /**
     * @param  list<array{name: string, quota_mb: int|null, unlimited: bool}>  $packages
     * @return array{name: string, quota_mb: int|null, unlimited: bool}|null
     */
    private function find(array $packages, string $name): ?array
    {
        foreach ($packages as $package)
        {
            if (strcasecmp($package['name'], $name) === 0)
            {
                return $package;
            }
        }

        return null;
    }

    /**
     * @param  array{name: string, quota_mb: int|null, unlimited: bool}|null  $current
     * @param  array{name: string, quota_mb: int|null, unlimited: bool}  $target
     */
    private function blockReason(?float $used, ?array $current, array $target, string $currentName): ?string
    {
        if (strcasecmp($target['name'], $currentName) === 0)
        {
            return 'Es el plan actual.';
        }

        if ($target['unlimited'] || $target['quota_mb'] === null)
        {
            return null;
        }

        $currentUnlimited = $current['unlimited'] ?? false;
        $currentQuota = $current['quota_mb'] ?? null;
        $goingDown = $currentUnlimited || ($currentQuota !== null && $target['quota_mb'] < $currentQuota);

        if (! $goingDown)
        {
            return null;
        }

        if ($used !== null && $used > $target['quota_mb'])
        {
            return 'El uso actual no entra en la capacidad de ese plan.';
        }

        return null;
    }

    /**
     * @return list<array{name: string, quota_mb: int|null, unlimited: bool}>
     */
    private function packages(Domain $domain): array
    {
        $domain->loadMissing('server');
        $server = $domain->server;

        if ($server !== null && $server->hasToken() && $this->controlPanelManager->supports($server))
        {
            $listed = $this->controlPanelManager->forServer($server)->listPlans($server);
            $packages = $listed['packages'] ?? null;

            if (is_array($packages) && $packages !== [])
            {
                return array_values(array_filter($packages, fn (mixed $package): bool => is_array($package) && filled($package['name'] ?? null)));
            }

            $names = $listed['plans'] ?? [];

            if (is_array($names) && $names !== [])
            {
                return array_map(fn (mixed $name): array => [
                    'name' => (string) $name,
                    'quota_mb' => null,
                    'unlimited' => false,
                ], array_filter($names, fn (mixed $name): bool => is_string($name) && $name !== ''));
            }
        }

        return array_map(fn (string $name): array => [
            'name' => $name,
            'quota_mb' => null,
            'unlimited' => false,
        ], $domain->getCachedAvailablePlans());
    }

    private function usedMb(Domain $domain): ?float
    {
        $cached = $domain->getCachedAccountDisk();

        if (is_array($cached) && isset($cached['used_mb']) && is_numeric($cached['used_mb']))
        {
            return (float) $cached['used_mb'];
        }

        $raw = data_get($domain->data, 'disk_used');

        return is_numeric($raw) ? (float) $raw : null;
    }
}
