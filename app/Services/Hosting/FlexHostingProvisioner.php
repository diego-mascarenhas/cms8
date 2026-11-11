<?php

namespace App\Services\Hosting;

use App\Models\Category;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use App\Services\ControlPanel\ControlPanelManager;
use Illuminate\Support\Str;
use RuntimeException;

class FlexHostingProvisioner
{
    public function __construct(
        private ControlPanelManager $controlPanelManager,
    ) {}

    /**
     * @param  list<string>  $mailboxes
     * @param  list<array{priority: int, target: string}>  $mxRecords
     * @return array{
     *     domain: Domain,
     *     password: string,
     *     plan: string,
     *     nameservers: list<string>,
     *     spf_configured: bool,
     *     mailboxes: list<array{email: string, password: string, created: bool, error: string|null}>,
     *     mail_dns_configured: bool|null,
     *     mail_dns_error: string|null
     * }
     */
    public function provision(User $user, Server $server, int $enterpriseId, string $domainName, string $username, array $mailboxes, array $mxRecords = []): array
    {
        $package = (string) config('humano_hosting.flex_package', 'revision_flex');
        $password = Str::password(16, letters: true, numbers: true, symbols: true);
        $contactEmail = $user->currentTeam?->getHostingContactEmail() ?? $user->email;

        if ($server->control_panel !== 'cpanel' || ! $server->hasToken())
        {
            throw new RuntimeException('El servidor no tiene credenciales configuradas para crear la cuenta.');
        }

        $connector = $this->controlPanelManager->forServer($server);
        $result = $connector->createAccount(
            $server,
            $username,
            $domainName,
            $package,
            $password,
            $contactEmail,
        );

        if (! ($result['success'] ?? false))
        {
            throw new RuntimeException($result['error'] ?? 'No se pudo crear la cuenta en cPanel.');
        }

        $domain = Domain::query()->create([
            'domain' => $domainName,
            'server_id' => $server->id,
            'service_id' => $this->resolveServiceId($user, $enterpriseId, $domainName, $username, $package, $server),
            'username' => $username,
            'plan' => $package,
            'suspended' => false,
            'needs_update' => false,
            'is_working' => true,
            'data' => [],
        ]);

        $spfConfigured = false;
        $spfRecord = $server->getProvisioningSpfRecord();

        if ($spfRecord !== '')
        {
            $spfResult = $connector->ensureSpfRecord($server, $domain, $spfRecord);
            $spfConfigured = (bool) ($spfResult['success'] ?? false);
        }

        $createdMailboxes = [];

        foreach ($mailboxes as $local)
        {
            $mailboxPassword = Str::password(16, letters: true, numbers: true, symbols: true);
            $emailResult = $connector->createEmailAccount($server, $domain, $local, $mailboxPassword);
            $created = (bool) ($emailResult['success'] ?? false);

            $createdMailboxes[] = [
                'email' => $local.'@'.$domain->domain,
                'password' => $mailboxPassword,
                'created' => $created,
                'error' => $created ? null : ($emailResult['error'] ?? 'No se pudo crear el correo.'),
            ];
        }

        $mailDnsConfigured = null;
        $mailDnsError = null;

        if ($mxRecords !== [])
        {
            $mxResult = $connector->updateMxRecords($server, $domain, $mxRecords);
            $mailDnsConfigured = (bool) ($mxResult['success'] ?? false);
            $mailDnsError = $mailDnsConfigured ? null : ($mxResult['error'] ?? 'No se pudieron cambiar los MX.');

            if ($mailDnsConfigured)
            {
                $domain->update([
                    'data' => array_merge($domain->data ?? [], [
                        'mx_records' => $mxRecords,
                    ]),
                ]);
            }
        }

        return [
            'domain' => $domain,
            'password' => $password,
            'plan' => (string) config('humano_hosting.flex_plan_name', 'Flex'),
            'nameservers' => $server->getProvisioningNameservers(),
            'spf_configured' => $spfConfigured,
            'mailboxes' => $createdMailboxes,
            'mail_dns_configured' => $mailDnsConfigured,
            'mail_dns_error' => $mailDnsError,
        ];
    }

    private function resolveServiceId(User $user, int $enterpriseId, string $domainName, string $username, string $package, Server $server): ?int
    {
        $categoryId = Category::query()
            ->where('name', 'like', '%Hosting%')
            ->value('id');

        if ($categoryId === null)
        {
            $categoryId = Category::query()->create([
                'name' => 'Hosting',
                'team_id' => $user->currentTeam?->id,
                'status' => true,
            ])->id;
        }

        $service = Service::query()->create([
            'enterprise_id' => $enterpriseId,
            'category_id' => $categoryId,
            'operation' => 'sell',
            'description' => 'Hosting '.$domainName,
            'data' => [
                'domain' => $domainName,
                'username' => $username,
                'plan' => $package,
                'server_id' => $server->id,
                'server_url' => $server->server_url,
            ],
            'responsible_id' => $user->id,
            'status' => 4,
        ]);

        return $service->id;
    }
}
