<?php

namespace App\Services\Hosting;

use App\Helpers\DnsHelper;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\ServiceSync;
use App\Models\Team;
use App\Models\User;
use App\Services\CollectionAssistantContextService;
use App\Services\ControlPanel\CpanelConnector;
use Illuminate\Support\Collection;

class HostingCustomerAssistantService
{
    public function __construct(
        private readonly CpanelConnector $cpanelConnector,
        private readonly DomainCpanelPasswordService $domainCpanelPasswordService,
        private readonly DomainMailboxPasswordService $domainMailboxPasswordService,
        private readonly CollectionAssistantContextService $collectionAssistantContext,
        private readonly HostingPlanSwitchService $planSwitch,
    ) {}

    public function listForContact(int $teamId, ?int $contactId): string
    {
        $domains = $this->domainsForContact($teamId, $contactId);
        if (is_string($domains))
        {
            return $domains;
        }

        $missing = $this->hostingNamesWithoutAccount($teamId, $contactId, $domains);
        if ($domains->isEmpty())
        {
            if ($missing === [])
            {
                return 'Este contacto no tiene un hosting asociado.';
            }

            return "Suscripciones de hosting sin cuenta en el servidor:\n".collect($missing)
                ->map(fn (string $name) => '- '.$name)
                ->implode("\n");
        }

        $lines = ['Hostings de este contacto (solo suscripciones de hosting):'];
        foreach ($domains as $domain)
        {
            $panel = (string) ($domain->server?->control_panel ?: 'sin panel');
            $state = $domain->suspended ? 'suspendido' : 'activo';
            $lines[] = '- '.$domain->domain.' · '.$panel.' · '.$state.($domain->plan ? ' · plan '.$domain->plan : '');
        }

        foreach ($missing as $name)
        {
            $lines[] = '- '.$name.' · suscripción de hosting sin cuenta en el servidor';
        }

        $cpanel = $this->cpanelDomains($domains);
        if ($cpanel->isEmpty())
        {
            $lines[] = 'Ninguno es cPanel, así que no se puede restablecer la clave, el correo ni el FTP desde aquí.';
        } elseif ($domains->count() > 1)
        {
            $lines[] = 'Hay varios dominios. Para cambiar una clave, crear una cuenta o pedir un upgrade, hay que indicar cuál.';
        }

        return implode("\n", $lines);
    }

    public function resetCpanelAccess(int $teamId, ?int $contactId, User $actor, ?string $domainName): string
    {
        $domain = $this->resolveCpanelDomain($teamId, $contactId, $domainName);
        if (is_string($domain))
        {
            return $domain;
        }

        $result = $this->domainCpanelPasswordService->resetAndOptionallyNotify(
            $actor,
            $domain,
            (string) $contactId,
            DomainCpanelPasswordService::NOTIFY_WHATSAPP,
        );

        return $this->notifyResult($result, 'La clave de cPanel se actualizó.');
    }

    public function manageMailbox(int $teamId, ?int $contactId, User $actor, string $action, ?string $domainName, ?string $account): string
    {
        $action = strtolower(trim($action));
        $domainName = $this->preferredDomainName($domainName, $account);

        if ($action === 'list' && $domainName === null)
        {
            $domains = $this->cpanelDomainsForContact($teamId, $contactId);
            if (is_string($domains))
            {
                return $domains;
            }

            return $this->listMailboxes($domains);
        }

        $domain = $this->resolveCpanelDomain($teamId, $contactId, $domainName);
        if (is_string($domain))
        {
            return $domain;
        }

        if ($action === 'list')
        {
            return $this->listMailboxes(collect([$domain]));
        }

        $localPart = $this->localPart($account);
        if ($localPart === null)
        {
            return 'Falta el nombre de la cuenta de correo (la parte antes de @).';
        }

        $mailbox = $localPart.'@'.$domain->domain;

        if ($action === 'settings')
        {
            return $this->domainCpanelPasswordService->buildMailboxAccessMessage($domain, $mailbox, '(la que ya tiene)');
        }

        if ($action === 'create')
        {
            $password = $this->domainCpanelPasswordService->generatePassword();
            $created = $this->cpanelConnector->createEmailAccount($domain->server, $domain, $localPart, $password);
            if (! ($created['success'] ?? false))
            {
                return (string) ($created['error'] ?? 'No se pudo crear la cuenta de correo.');
            }

            $message = $this->domainCpanelPasswordService->buildMailboxAccessMessage($domain, $mailbox, $password);

            return $this->notifyResult(array_merge(
                ['success' => true, 'access_message' => $message],
                $this->domainCpanelPasswordService->sendAccessNotification(
                    $actor,
                    $domain,
                    $message,
                    'Acceso correo '.$mailbox,
                    (string) $contactId,
                    DomainCpanelPasswordService::NOTIFY_WHATSAPP,
                ),
            ), 'La cuenta '.$mailbox.' quedó creada.');
        }

        if ($action === 'reset_password')
        {
            $result = $this->domainMailboxPasswordService->resetAndOptionallyNotify(
                $actor,
                $domain,
                $mailbox,
                (string) $contactId,
                DomainCpanelPasswordService::NOTIFY_WHATSAPP,
            );

            return $this->notifyResult($result, 'La clave de '.$mailbox.' se actualizó.');
        }

        return 'Acción de correo no válida. Usá list, create, reset_password o settings.';
    }

    public function manageFtp(int $teamId, ?int $contactId, User $actor, string $action, ?string $domainName, ?string $account): string
    {
        $action = strtolower(trim($action));
        $domainName = $this->preferredDomainName($domainName, $account);

        if ($action === 'list' && $domainName === null)
        {
            $domains = $this->cpanelDomainsForContact($teamId, $contactId);
            if (is_string($domains))
            {
                return $domains;
            }

            return $this->listFtpAccounts($domains);
        }

        $domain = $this->resolveCpanelDomain($teamId, $contactId, $domainName);
        if (is_string($domain))
        {
            return $domain;
        }

        if ($action === 'list')
        {
            return $this->listFtpAccounts(collect([$domain]));
        }

        $localPart = $this->localPart($account);
        if ($localPart === null)
        {
            return 'Falta el usuario FTP.';
        }

        $login = $localPart.'@'.$domain->domain;

        if ($action === 'settings')
        {
            return $this->domainCpanelPasswordService->buildFtpAccessMessage($domain, $login, '(la que ya tiene)');
        }

        $password = $this->domainCpanelPasswordService->generatePassword();

        if ($action === 'create')
        {
            $created = $this->cpanelConnector->createFtpAccount($domain->server, $domain, $localPart, $password);
            if (! ($created['success'] ?? false))
            {
                return (string) ($created['error'] ?? 'No se pudo crear la cuenta FTP.');
            }
        } elseif ($action === 'reset_password')
        {
            $changed = $this->cpanelConnector->changeFtpPassword($domain->server, $domain, $localPart, $password);
            if (! ($changed['success'] ?? false))
            {
                return (string) ($changed['error'] ?? 'No se pudo cambiar la clave FTP.');
            }
        } else
        {
            return 'Acción de FTP no válida. Usá list, create, reset_password o settings.';
        }

        $message = $this->domainCpanelPasswordService->buildFtpAccessMessage($domain, $login, $password);

        return $this->notifyResult(array_merge(
            ['success' => true, 'access_message' => $message],
            $this->domainCpanelPasswordService->sendAccessNotification(
                $actor,
                $domain,
                $message,
                'Acceso FTP '.$login,
                (string) $contactId,
                DomainCpanelPasswordService::NOTIFY_WHATSAPP,
            ),
        ), $action === 'create' ? 'La cuenta FTP '.$login.' quedó creada.' : 'La clave FTP de '.$login.' se actualizó.');
    }

    public function dnsInfo(int $teamId, ?int $contactId, ?string $domainName): string
    {
        $domains = $this->selectedDomains($teamId, $contactId, $domainName);
        if (is_string($domains))
        {
            return $domains;
        }

        return $domains
            ->map(fn (Domain $domain) => $this->formatDns($domain))
            ->implode("\n\n")."\n\n".$this->revisionAlphaSpfSuggestion();
    }

    public function productLinks(?string $product): string
    {
        $catalog = $this->productCatalog();
        $query = strtolower(trim((string) $product));
        if ($query === '')
        {
            return "Páginas de REVISION ALPHA. Pasá el link que corresponda:\n".$this->formatProductCatalog($catalog);
        }

        $best = null;
        $bestLength = 0;
        foreach ($catalog as $item)
        {
            foreach ($item['keys'] as $key)
            {
                if (($query === $key || str_contains($query, $key)) && strlen($key) > $bestLength)
                {
                    $best = $item;
                    $bestLength = strlen($key);
                }
            }
        }

        if ($best === null)
        {
            return 'No hay una página pública para «'.$product."». Estas son las que sí:\n".$this->formatProductCatalog($catalog);
        }

        return $best['label'].":\n".$this->formatProductCatalog([$best]);
    }

    public function googleMailSetup(int $teamId, ?int $contactId, ?string $domainName): string
    {
        $domains = $this->selectedDomains($teamId, $contactId, $domainName);
        if (is_string($domains))
        {
            return $domains;
        }

        $steps = implode("\n", [
            'Para usar el correo del dominio en Gmail (Google Workspace):',
            '1. Entrá a https://admin.google.com con el administrador de esa cuenta.',
            '2. Agregá el dominio y verificá con el registro TXT que muestra Google. Ese código es de tu cuenta: no lo inventes.',
            '3. En el DNS, reemplazá los MX actuales por uno solo:',
            '   1 smtp.google.com',
            '   No lo mezcles con los MX viejos de Google (ASPMX.L.GOOGLE.COM y los ALT).',
            '4. SPF: siempre el de REVISION ALPHA, en un solo TXT:',
            '   '.DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT,
            '   Si el correo también sale por Google, no crees otro. Usá uno solo:',
            '   v=spf1 include:spf.revisionalpha.com include:_spf.google.com -all',
            '5. DKIM: en Admin de Google, Gmail, Autenticar el correo, generá la clave y publicá el TXT que te indique. Sin esa clave no queda cerrado.',
            '6. La propagación puede tardar unas horas.',
            '',
            'No cambié los DNS.',
        ]);

        $current = $domains
            ->map(fn (Domain $domain) => $this->formatDns($domain))
            ->implode("\n\n");

        return $steps."\n\nMX y DNS actuales:\n\n".$current;
    }

    public function unpaidInvoices(int $teamId, ?int $contactId): string
    {
        if ($contactId === null || $contactId < 1)
        {
            return 'No hay un contacto en este hilo para consultar la deuda.';
        }

        $links = $this->collectionAssistantContext->paymentLinksMessageForContact($contactId, $teamId);
        $total = $this->collectionAssistantContext->unpaidTotalLabelForContact($contactId, $teamId);

        if ($links === null && $total === null)
        {
            return 'No hay facturas impagas con link de pago para este contacto.';
        }

        $lines = [];
        if ($total !== null)
        {
            $lines[] = 'Deuda pendiente: '.$total;
        }
        if ($links !== null)
        {
            $lines[] = $links;
        }

        return implode("\n\n", $lines);
    }

    public function requestUpgrade(int $teamId, ?int $contactId, User $actor, ?string $domainName, ?string $plan): string
    {
        $resolved = $this->resolveUpgrade($teamId, $contactId, $domainName);
        if (is_string($resolved))
        {
            return $resolved;
        }

        return $this->planSwitch->apply($resolved['team'], $resolved['domain'], $resolved['subscription'], $plan);
    }

    /**
     * @return array{team: Team, domain: Domain, subscription: ServiceSync}|string
     */
    private function resolveUpgrade(int $teamId, ?int $contactId, ?string $domainName): array|string
    {
        $domains = $this->domainsForContact($teamId, $contactId);
        if (is_string($domains))
        {
            return $domains;
        }

        $domain = $this->matchDomain($domains, $domainName);
        if (is_string($domain))
        {
            return $domain;
        }

        $subscriptions = $this->hostingSubscriptionsForContact($teamId, $contactId);
        if (is_string($subscriptions))
        {
            return $subscriptions;
        }

        $domainKey = strtolower((string) $domain->domain);
        $subscription = $subscriptions->first(
            fn (ServiceSync $sync): bool => $sync->hostingDomainCandidate() === $domainKey,
        );

        if (! $subscription instanceof ServiceSync)
        {
            return 'Este dominio no tiene una suscripción de hosting activa. No cambié el plan del servidor.';
        }

        $team = Team::withoutGlobalScopes()->find($teamId);
        if (! $team instanceof Team)
        {
            return 'No encontré el equipo. No cambié el plan.';
        }

        return [
            'team' => $team,
            'domain' => $domain,
            'subscription' => $subscription,
        ];
    }

    public function rehearseUpgrade(int $teamId, ?int $contactId, ?string $domainName, ?string $plan): string
    {
        $resolved = $this->resolveUpgrade($teamId, $contactId, $domainName);
        if (is_string($resolved))
        {
            return $resolved;
        }

        return $this->planSwitch->preview($resolved['team'], $resolved['domain'], $resolved['subscription'], $plan);
    }

    /**
     * @return Collection<int, Domain>|string
     */
    private function selectedDomains(int $teamId, ?int $contactId, ?string $domainName): Collection|string
    {
        $domains = $this->domainsForContact($teamId, $contactId);
        if (is_string($domains))
        {
            return $domains;
        }

        if ($domains->isEmpty())
        {
            return 'Este contacto no tiene un hosting asociado.';
        }

        if (trim((string) $domainName) === '')
        {
            return $domains;
        }

        $picked = $this->matchDomain($domains, $domainName);
        if (is_string($picked))
        {
            return $picked;
        }

        return collect([$picked]);
    }

    private function revisionAlphaSpfSuggestion(): string
    {
        return "SPF de REVISION ALPHA (publicalo en el dominio, un solo TXT):\n".DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT;
    }

    /**
     * @return list<array{label: string, keys: list<string>, pages: list<array{0: string, 1: string}>}>
     */
    private function productCatalog(): array
    {
        $origin = 'https://revisionalpha.com';

        return [
            ['label' => 'Web hosting', 'keys' => ['web hosting', 'hosting', 'alojamiento', 'cpanel'], 'pages' => [['Página', $origin.'/hosting'], ['Stack', $origin.'/hosting/stack']]],
            ['label' => 'WordPress', 'keys' => ['wordpress'], 'pages' => [['Página', $origin.'/wordpress'], ['Stack', $origin.'/wordpress/stack']]],
            ['label' => 'Cloud', 'keys' => ['cloud'], 'pages' => [['Página', $origin.'/cloud'], ['Stack', $origin.'/cloud/stack']]],
            ['label' => 'Servidores dedicados', 'keys' => ['servidores dedicados', 'dedicado', 'vps'], 'pages' => [['Página', $origin.'/servidores-dedicados'], ['Stack', $origin.'/servidores-dedicados/stack']]],
            ['label' => 'Hosting para agencias', 'keys' => ['hosting para agencias', 'agencia'], 'pages' => [['Página', $origin.'/hosting-agency']]],
            ['label' => 'Emailer', 'keys' => ['emailer', 'email marketing'], 'pages' => [['Página', $origin.'/emailer'], ['Stack', $origin.'/emailer/stack']]],
            ['label' => 'Mi socio digital', 'keys' => ['mi socio digital', 'socio digital'], 'pages' => [['Página', $origin.'/mi-socio-digital']]],
            ['label' => 'Auditoría, consultoría y desarrollo', 'keys' => ['auditoría', 'auditoria', 'consultoría', 'consultoria', 'desarrollo a medida'], 'pages' => [['Página', $origin.'/auditoria-consultoria-desarrollo']]],
            ['label' => 'Telefonía digital', 'keys' => ['telefonía', 'telefonia', 'click to call'], 'pages' => [['Página', $origin.'/simple-click-to-call']]],
            ['label' => 'Niveles de soporte', 'keys' => ['soporte'], 'pages' => [['Página', $origin.'/niveles-de-soporte']]],
            ['label' => 'Contacto', 'keys' => ['contacto', 'reunión', 'reunion'], 'pages' => [['Página', $origin.'/contactenos']]],
            ['label' => 'Estado de servicios', 'keys' => ['estado de servicios', 'status'], 'pages' => [['Página', $origin.'/status']]],
            ['label' => 'Conformidad', 'keys' => ['conformidad'], 'pages' => [['Página', $origin.'/conformidad']]],
            ['label' => 'Acuerdo de servicio', 'keys' => ['acuerdo de servicio', 'sla'], 'pages' => [['Página', $origin.'/sla']]],
            ['label' => 'Datacenter', 'keys' => ['datacenter'], 'pages' => [['Página', $origin.'/el-datacenter']]],
        ];
    }

    /**
     * @param  list<array{label: string, keys: list<string>, pages: list<array{0: string, 1: string}>}>  $items
     */
    private function formatProductCatalog(array $items): string
    {
        $lines = [];
        foreach ($items as $item)
        {
            $lines[] = $item['label'].':';
            foreach ($item['pages'] as [$name, $url])
            {
                $lines[] = '- '.$name.': '.$url;
            }
        }

        return implode("\n", $lines);
    }

    private function formatDns(Domain $domain): string
    {
        $domain->loadMissing('server');
        $info = $domain->getCachedDisplayInfo();
        $lines = ['DNS de '.$domain->domain];

        $nameservers = array_values(array_filter($info['nameservers'] ?? []));
        $lines[] = 'Nameservers:';
        $lines[] = $nameservers === []
            ? '- (no hay registro)'
            : collect($nameservers)->map(fn (string $ns) => '- '.$ns)->implode("\n");

        $addresses = $this->addressRecords($domain);
        if ($addresses === [] && filled($info['web_ip'] ?? null))
        {
            $addresses = [(string) $info['web_ip']];
        }
        if ($addresses !== [])
        {
            $lines[] = 'A: '.implode(', ', $addresses);
        }

        $mx = $this->mxRecords($domain);
        $lines[] = 'MX:';
        $lines[] = $mx === []
            ? '- (no hay registro)'
            : collect($mx)->map(fn (array $row) => '- '.$row['priority'].' '.$row['target'])->implode("\n");

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function addressRecords(Domain $domain): array
    {
        $records = $domain->data['dns_records']['a'] ?? [];
        if (! is_array($records))
        {
            return [];
        }

        return collect($records)
            ->map(fn (mixed $row) => is_array($row) ? (string) ($row['ip'] ?? '') : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{priority: int, target: string}>
     */
    private function mxRecords(Domain $domain): array
    {
        $cached = $this->normalizeMx($domain->data['mx_records'] ?? []);
        if ($cached !== [])
        {
            return $cached;
        }

        $fromDns = $this->normalizeMx($domain->data['dns_records']['mx'] ?? []);
        if ($fromDns !== [])
        {
            return $fromDns;
        }

        if ($domain->server?->control_panel !== 'cpanel' || ! $domain->server->hasToken() || $domain->suspended)
        {
            return [];
        }

        $live = $this->cpanelConnector->getMxRecords($domain->server, $domain);
        if (! ($live['success'] ?? false))
        {
            return [];
        }

        return $this->normalizeMx($live['records'] ?? []);
    }

    /**
     * @return list<array{priority: int, target: string}>
     */
    private function normalizeMx(mixed $records): array
    {
        if (! is_array($records))
        {
            return [];
        }

        return collect($records)
            ->filter(fn (mixed $row) => is_array($row))
            ->map(function (array $row): ?array
            {
                $target = rtrim((string) ($row['target'] ?? $row['mx'] ?? ''), '.');
                if ($target === '')
                {
                    return null;
                }

                return [
                    'priority' => (int) ($row['priority'] ?? $row['pri'] ?? 0),
                    'target' => $target,
                ];
            })
            ->filter()
            ->sortBy('priority')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function notifyResult(array $result, string $done): string
    {
        if (! ($result['success'] ?? false))
        {
            return (string) ($result['error'] ?? 'No se pudo completar la operación.');
        }

        if (($result['notified'] ?? false) === true)
        {
            return $done.' Los datos de acceso ya se enviaron por WhatsApp. No repitas la contraseña.';
        }

        $message = trim((string) ($result['access_message'] ?? ''));
        $warning = trim((string) ($result['warning'] ?? ''));

        return $done."\n".($warning !== '' ? $warning."\n" : '')."Pegá este mensaje tal cual:\n".$message;
    }

    /**
     * @return Collection<int, ServiceSync>|string
     */
    private function hostingSubscriptionsForContact(int $teamId, ?int $contactId): Collection|string
    {
        if ($contactId === null || $contactId < 1)
        {
            return 'No hay un contacto en este hilo para identificar el hosting.';
        }

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->with('enterprises')
            ->find($contactId);

        if (! $contact)
        {
            return 'El contacto de este hilo no está en el equipo.';
        }

        $codes = $contact->enterprises
            ->filter(fn ($enterprise) => (int) $enterprise->team_id === $teamId)
            ->map(fn ($enterprise) => trim((string) $enterprise->code))
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty())
        {
            return 'Este contacto no está vinculado a una empresa con hosting.';
        }

        $subscriptions = ServiceSync::query()
            ->where('team_id', $teamId)
            ->whereIn('customer_id', $codes->all())
            ->get()
            ->filter(fn (ServiceSync $sync) => $sync->isClientHosting() && $this->subscriptionGrantsHosting($sync))
            ->values();

        if ($subscriptions->isEmpty())
        {
            return 'Este contacto no tiene una suscripción de hosting. Una suscripción de asesoramiento u otro servicio no cuenta.';
        }

        return $subscriptions;
    }

    /**
     * @return Collection<int, Domain>|string
     */
    private function domainsForContact(int $teamId, ?int $contactId): Collection|string
    {
        $subscriptions = $this->hostingSubscriptionsForContact($teamId, $contactId);
        if (is_string($subscriptions))
        {
            return $subscriptions;
        }

        $names = $subscriptions
            ->map(fn (ServiceSync $sync) => $sync->hostingDomainCandidate())
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty())
        {
            return 'La suscripción de hosting no tiene el dominio en la metadata.';
        }

        return Domain::query()
            ->with('server')
            ->where(function ($query) use ($names): void
            {
                foreach ($names as $name)
                {
                    $query->orWhereRaw('LOWER(domain) = ?', [$name]);
                }
            })
            ->orderBy('domain')
            ->get();
    }

    /**
     * @param  Collection<int, Domain>  $domains
     * @return list<string>
     */
    private function hostingNamesWithoutAccount(int $teamId, ?int $contactId, Collection $domains): array
    {
        $subscriptions = $this->hostingSubscriptionsForContact($teamId, $contactId);
        if (is_string($subscriptions))
        {
            return [];
        }

        $present = $domains
            ->map(fn (Domain $domain) => strtolower((string) $domain->domain))
            ->all();

        return $subscriptions
            ->map(fn (ServiceSync $sync) => $sync->hostingDomainCandidate())
            ->filter(fn (?string $name) => $name !== null && ! in_array($name, $present, true))
            ->unique()
            ->values()
            ->all();
    }

    private function subscriptionGrantsHosting(ServiceSync $sync): bool
    {
        return in_array(strtolower(trim((string) $sync->status)), [
            'active',
            'past_due',
            'trialing',
            'unpaid',
        ], true);
    }

    /**
     * @param  Collection<int, Domain>  $domains
     */
    private function listMailboxes(Collection $domains): string
    {
        $lines = ['Cuentas de correo:'];

        foreach ($domains as $domain)
        {
            $listed = $this->cpanelConnector->listEmailAccounts($domain->server, $domain);
            $lines[] = $domain->domain.':';
            if (! ($listed['success'] ?? false))
            {
                $lines[] = '- '.($listed['error'] ?? 'no se pudo listar');

                continue;
            }

            $emails = collect($listed['emails'] ?? [])->pluck('email')->filter()->values();
            if ($emails->isEmpty())
            {
                $lines[] = '- (ninguna)';

                continue;
            }

            foreach ($emails as $email)
            {
                $lines[] = '- '.$email;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Domain>  $domains
     */
    private function listFtpAccounts(Collection $domains): string
    {
        $lines = ['Cuentas FTP:'];

        foreach ($domains as $domain)
        {
            $listed = $this->cpanelConnector->listFtpAccounts($domain->server, $domain);
            $lines[] = $domain->domain.':';
            if (! ($listed['success'] ?? false))
            {
                $lines[] = '- '.($listed['error'] ?? 'no se pudo listar');

                continue;
            }

            $accounts = collect($listed['accounts'] ?? []);
            if ($accounts->isEmpty())
            {
                $lines[] = '- (ninguna)';

                continue;
            }

            foreach ($accounts as $row)
            {
                $lines[] = '- '.$row['user'].($row['homedir'] !== '' ? ' · '.$row['homedir'] : '');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return Collection<int, Domain>|string
     */
    private function cpanelDomainsForContact(int $teamId, ?int $contactId): Collection|string
    {
        $domains = $this->domainsForContact($teamId, $contactId);
        if (is_string($domains))
        {
            return $domains;
        }

        $cpanel = $this->cpanelDomains($domains);
        if ($cpanel->isEmpty())
        {
            return $domains->isEmpty()
                ? 'Este contacto no tiene un hosting asociado.'
                : 'El hosting no es cPanel, así que no se puede gestionar la clave, el correo ni el FTP desde aquí.';
        }

        return $cpanel;
    }

    /**
     * @param  Collection<int, Domain>  $domains
     */
    private function resolveCpanelDomain(int $teamId, ?int $contactId, ?string $domainName): Domain|string
    {
        $domains = $this->cpanelDomainsForContact($teamId, $contactId);
        if (is_string($domains))
        {
            return $domains;
        }

        return $this->matchDomain($domains, $domainName);
    }

    /**
     * @param  Collection<int, Domain>  $domains
     */
    private function matchDomain(Collection $domains, ?string $domainName): Domain|string
    {
        $name = strtolower(trim((string) $domainName));
        if (str_contains($name, '@'))
        {
            $name = substr($name, strrpos($name, '@') + 1);
        }
        $name = preg_replace('/^www\./', '', $name) ?? $name;

        if ($name !== '')
        {
            $exact = $domains->first(fn (Domain $domain) => strtolower((string) $domain->domain) === $name);
            if ($exact instanceof Domain)
            {
                return $exact;
            }

            $partial = $domains->filter(function (Domain $domain) use ($name): bool
            {
                $host = strtolower((string) $domain->domain);

                return str_contains($host, $name) || str_contains($name, $host);
            })->values();

            if ($partial->count() === 1)
            {
                return $partial->first();
            }

            if ($partial->count() > 1)
            {
                return $this->askWhichDomain($partial);
            }

            $missing = 'No encontré el dominio '.$name.' entre los hostings de este contacto.';

            return $domains->count() > 1
                ? $missing."\n".$this->askWhichDomain($domains)
                : $missing;
        }

        if ($domains->count() === 1)
        {
            return $domains->first();
        }

        return $this->askWhichDomain($domains);
    }

    /**
     * @param  Collection<int, Domain>  $domains
     */
    private function askWhichDomain(Collection $domains): string
    {
        $list = $domains->pluck('domain')->map(fn (string $domain) => '- '.$domain)->implode("\n");

        return "Hay más de un hosting. Preguntá cuál antes de cambiar nada:\n".$list;
    }

    private function preferredDomainName(?string $domainName, ?string $account): ?string
    {
        $domainName = trim((string) $domainName);
        if ($domainName !== '')
        {
            return $domainName;
        }

        $account = strtolower(trim((string) $account));
        if (! str_contains($account, '@'))
        {
            return null;
        }

        $domain = substr($account, strrpos($account, '@') + 1);

        return $domain !== '' ? $domain : null;
    }

    /**
     * @param  Collection<int, Domain>  $domains
     * @return Collection<int, Domain>
     */
    private function cpanelDomains(Collection $domains): Collection
    {
        return $domains
            ->filter(function (Domain $domain): bool
            {
                return $domain->server?->control_panel === 'cpanel'
                    && $domain->server->hasToken()
                    && ! $domain->suspended;
            })
            ->values();
    }

    private function localPart(?string $account): ?string
    {
        $value = strtolower(trim((string) $account));
        if (str_contains($value, '@'))
        {
            $value = explode('@', $value, 2)[0];
        }

        if ($value === '' || preg_match('/^[a-z0-9][a-z0-9._-]{0,31}$/', $value) !== 1)
        {
            return null;
        }

        return $value;
    }
}
