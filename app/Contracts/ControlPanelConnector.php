<?php

namespace App\Contracts;

use App\Models\Domain;
use App\Models\Server;

interface ControlPanelConnector
{
    public function supports(Server $server): bool;

    /**
     * @return array{success: bool, error?: string, version?: string|null, hostname?: string|null}
     */
    public function testConnection(Server $server): array;

    /**
     * @return array{success: bool, error?: string, domains?: \Illuminate\Support\Collection<int, array<string, mixed>>}
     */
    public function listAccounts(Server $server): array;

    /**
     * @return array{success: bool, error?: string, domains_synced?: int, total_domains?: int}
     */
    public function syncAccounts(Server $server): array;

    /**
     * @return array{success: bool, error?: string, plans?: array<int, string>}
     */
    public function listPlans(Server $server): array;

    /**
     * @return array{success: bool, error?: string}
     */
    public function changePlan(Server $server, Domain $domain, string $plan): array;

    /**
     * @return array{success: bool, error?: string, emails?: array<int, array<string, mixed>>}
     */
    public function listEmailAccounts(Server $server, Domain $domain): array;

    /**
     * @return array{success: bool, error?: string}
     */
    public function changeEmailPassword(Server $server, Domain $domain, string $email, string $password): array;

    /**
     * @return array{success: bool, error?: string, records?: array<int, array<string, mixed>>}
     */
    public function getMxRecords(Server $server, Domain $domain): array;

    /**
     * @param  array<int, array{priority: int, target: string}>  $records
     * @return array{success: bool, error?: string}
     */
    public function updateMxRecords(Server $server, Domain $domain, array $records): array;
}
