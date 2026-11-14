<?php

namespace App\Services\Hosting;

use App\Models\Domain;
use App\Models\Team;
use App\Services\ControlPanel\ControlPanelManager;

class HostingPlanCatalog
{
    public function __construct(
        private readonly ControlPanelManager $controlPanelManager,
        private readonly StripeHostingPlanGateway $stripe,
    ) {}

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

        $packages = $this->pricedPackages($domain);
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
     * Active Stripe pricing packages present on the server, plus Flex and the current plan.
     * Ordered by the cheapest matching Stripe price (Beginner → Explorer → Enthusiast…), Flex last.
     *
     * @return list<array{name: string, quota_mb: int|null, unlimited: bool}>
     */
    private function pricedPackages(Domain $domain): array
    {
        $available = $this->packages($domain);
        $names = array_map(fn (array $package): string => $package['name'], $available);
        /** @var array<string, int> $rank */
        $rank = [];

        $team = $this->teamFor($domain);
        if ($team !== null)
        {
            foreach ($this->stripe->catalog($team) as $price)
            {
                $package = $this->packageFor($price, $names);
                if ($package === null)
                {
                    continue;
                }

                $key = strtolower($package);
                $amount = (int) ($price['unit_amount'] ?? 0);
                if (! isset($rank[$key]) || $amount < $rank[$key])
                {
                    $rank[$key] = $amount;
                }
            }
        }

        foreach ($names as $name)
        {
            if ($this->isFlex($name))
            {
                $rank[strtolower($name)] = PHP_INT_MAX - 1;
            }
        }

        $current = trim((string) $domain->plan);
        if ($current !== '' && ! isset($rank[strtolower($current)]))
        {
            $rank[strtolower($current)] = PHP_INT_MAX;
        }

        $filtered = array_values(array_filter(
            $available,
            fn (array $package): bool => isset($rank[strtolower($package['name'])]),
        ));

        usort($filtered, function (array $left, array $right) use ($rank): int
        {
            $leftRank = $rank[strtolower($left['name'])] ?? PHP_INT_MAX;
            $rightRank = $rank[strtolower($right['name'])] ?? PHP_INT_MAX;
            if ($leftRank !== $rightRank)
            {
                return $leftRank <=> $rightRank;
            }

            return strcasecmp($left['name'], $right['name']);
        });

        return $filtered;
    }

    /**
     * @param  array{plan: string, whm_package: string|null}  $price
     * @param  list<string>  $packages
     */
    private function packageFor(array $price, array $packages): ?string
    {
        $explicit = trim((string) ($price['whm_package'] ?? ''));
        if ($explicit !== '')
        {
            $found = array_values(array_filter(
                $packages,
                fn (string $package): bool => strcasecmp($package, $explicit) === 0,
            ));

            if (count($found) === 1)
            {
                return $found[0];
            }
        }

        $slug = strtolower(trim($price['plan']));
        $candidates = array_values(array_unique(array_filter([
            $slug,
            preg_match('/^(hosting|cloud|vps)-[a-z0-9]+$/', $slug)
                ? 'revision_'.substr($slug, strrpos($slug, '-') + 1)
                : null,
        ])));

        foreach ($candidates as $candidate)
        {
            $found = array_values(array_filter(
                $packages,
                fn (string $package): bool => strcasecmp($package, $candidate) === 0,
            ));

            if (count($found) === 1)
            {
                return $found[0];
            }
        }

        return null;
    }

    private function teamFor(Domain $domain): ?Team
    {
        $domain->loadMissing('server.team');
        $team = $domain->server?->team;

        return $team instanceof Team ? $team : null;
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
        $isCpanel = $server !== null && strtolower((string) $server->control_panel) === 'cpanel';

        if ($server !== null && $server->hasToken() && $this->controlPanelManager->supports($server))
        {
            $listed = $this->controlPanelManager->forServer($server)->listPlans($server);
            $packages = $listed['packages'] ?? null;

            if (is_array($packages) && $packages !== [])
            {
                return array_values(array_filter(
                    $packages,
                    fn (mixed $package): bool => is_array($package) && filled($package['name'] ?? null),
                ));
            }

            $names = $listed['plans'] ?? [];

            if (is_array($names) && $names !== [])
            {
                return array_map(fn (mixed $name): array => [
                    'name' => (string) $name,
                    'quota_mb' => null,
                    'unlimited' => false,
                ], array_filter(
                    $names,
                    fn (mixed $name): bool => is_string($name) && $name !== '',
                ));
            }

            // cPanel accounts must not fall back to a local catalog of unrelated plans.
            if ($isCpanel)
            {
                $current = trim((string) $domain->plan);

                return $current !== ''
                    ? [['name' => $current, 'quota_mb' => null, 'unlimited' => false]]
                    : [];
            }
        }

        if ($isCpanel)
        {
            $current = trim((string) $domain->plan);

            return $current !== ''
                ? [['name' => $current, 'quota_mb' => null, 'unlimited' => false]]
                : [];
        }

        return array_map(fn (string $name): array => [
            'name' => $name,
            'quota_mb' => null,
            'unlimited' => false,
        ], array_values($domain->getCachedAvailablePlans()));
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
