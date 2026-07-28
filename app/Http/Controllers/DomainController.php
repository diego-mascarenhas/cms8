<?php

namespace App\Http\Controllers;

use App\DataTables\DomainDataTable;
use App\Helpers\DnsHelper;
use App\Http\Requests\ResetDomainCpanelPasswordRequest;
use App\Http\Requests\StoreDomainEmailRequest;
use App\Http\Requests\UpdateDomainEmailPasswordRequest;
use App\Jobs\RefreshDomainDataJob;
use App\Models\Domain;
use App\Models\Server;
use App\Services\ControlPanel\ControlPanelManager;
use App\Services\Hosting\DomainCpanelPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DomainController extends Controller
{
    public function __construct(
        private ControlPanelManager $controlPanelManager,
        private DomainCpanelPasswordService $domainCpanelPasswordService,
    ) {
        $this->middleware('can:access-infrastructure-modules');
    }

    public function index(DomainDataTable $dataTable)
    {
        $servers = Server::all();

        return $dataTable->render('domain.index', compact('servers'));
    }

    public function create(): View
    {
        $servers = Server::all();

        return view('domain.create', compact('servers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string|unique:domains,domain',
            'server_id' => 'required|integer|exists:servers,id',
            'username' => 'required|string',
            'plan' => 'nullable|string',
            'site_type' => 'nullable|string',
            'php_version' => 'nullable|string',
            'notes' => 'nullable|string',
            'suspended' => 'boolean',
            'needs_update' => 'boolean',
            'is_working' => 'boolean',
        ]);

        $validated['suspended'] = $request->boolean('suspended');
        $validated['needs_update'] = $request->boolean('needs_update');
        $validated['is_working'] = $request->boolean('is_working', true);
        $validated['data'] = [];

        $domain = Domain::create($validated);

        return redirect()->route('domain.show', $domain->id)
            ->with('success', 'Domain created successfully.');
    }

    public function show(Domain $domain): View
    {
        $domain->load(['server', 'service.enterprise.contacts']);

        $controlPanelType = $domain->server?->control_panel;
        $accountIsSuspended = $domain->suspended;
        $displayInfo = $domain->getCachedDisplayInfo();
        $publicSpfCheck = $domain->getCachedPublicSpfCheck();
        $recommendedSpf = $domain->server?->getProvisioningSpfRecord()
            ?: DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT;
        $requiredNameservers = $domain->server?->getProvisioningNameservers()
            ?: config('humano_hosting.default_nameservers', []);
        $currentNameservers = $displayInfo['nameservers'] ?? [];
        $nameserversMatch = $this->nameserversMatch($currentNameservers, $requiredNameservers);
        $webmailUrl = $domain->server?->getWebmailUrl();
        $cpanelUrl = $domain->server?->getCpanelUrl();
        $emailAccounts = $domain->getCachedEmailAccounts();
        $mxRecords = $domain->getCachedMxRecords();
        $availablePlans = $domain->getCachedAvailablePlans();
        $accountDisk = $domain->getCachedAccountDisk();
        $controlPanelError = $domain->getCachedControlPanelError();
        $cpanelNotifiableContacts = $this->domainCpanelPasswordService->notifiableContactsForDomain($domain);
        $canResetCpanelPassword = ($controlPanelType === 'cpanel')
            && $domain->server?->hasToken()
            && ! $accountIsSuspended
            && filled($domain->username);

        if ($domain->server && $this->controlPanelManager->supports($domain->server))
        {
            if ($domain->server->control_panel === 'plesk')
            {
                $controlPanelError = $controlPanelError ?: 'Plesk account management will be available soon.';
            } elseif ($domain->server->control_panel === 'cpanel' && ! $domain->server->hasToken())
            {
                $controlPanelError = $controlPanelError ?: 'Configure the server WHM token to manage this account from Humano.';
            }
        }

        return view('domain.show', compact(
            'domain',
            'availablePlans',
            'emailAccounts',
            'mxRecords',
            'accountDisk',
            'controlPanelError',
            'controlPanelType',
            'accountIsSuspended',
            'displayInfo',
            'publicSpfCheck',
            'recommendedSpf',
            'requiredNameservers',
            'currentNameservers',
            'nameserversMatch',
            'webmailUrl',
            'cpanelUrl',
            'cpanelNotifiableContacts',
            'canResetCpanelPassword',
        ));
    }

    /**
     * @param  array<int, string>  $current
     * @param  array<int, string>  $required
     */
    private function nameserversMatch(array $current, array $required): bool
    {
        if ($required === [] || $current === [])
        {
            return false;
        }

        $normalize = static fn (array $nameservers): array => collect($nameservers)
            ->map(fn ($nameserver) => strtolower(rtrim((string) $nameserver, '.')))
            ->sort()
            ->values()
            ->all();

        return $normalize($current) === $normalize($required);
    }

    public function edit(Domain $domain): View
    {
        $servers = Server::all();

        return view('domain.edit', compact('domain', 'servers'));
    }

    public function update(Request $request, Domain $domain): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => [
                'required',
                'string',
                Rule::unique('domains')->ignore($domain->id),
            ],
            'server_id' => 'required|integer|exists:servers,id',
            'username' => 'required|string',
            'plan' => 'nullable|string',
            'site_type' => 'nullable|string',
            'php_version' => 'nullable|string',
            'notes' => 'nullable|string',
            'suspended' => 'boolean',
            'needs_update' => 'boolean',
            'is_working' => 'boolean',
        ]);

        $validated['suspended'] = $request->boolean('suspended');
        $validated['needs_update'] = $request->boolean('needs_update');
        $validated['is_working'] = $request->boolean('is_working', true);

        $domain->update($validated);

        return redirect()->route('domain.show', $domain->id)
            ->with('success', 'Domain updated successfully.');
    }

    public function destroy(Domain $domain): RedirectResponse
    {
        $domain->delete();

        return redirect()->route('hosting.index')
            ->with('success', 'Domain deleted successfully.');
    }

    public function refresh(Domain $domain): RedirectResponse
    {
        $domain->load('server');

        if (! $domain->server || $domain->server->control_panel !== 'cpanel' || ! $domain->server->hasToken())
        {
            return redirect()->route('domain.show', $domain->id)
                ->with('warning', 'La actualización requiere un servidor cPanel con credenciales configuradas.');
        }

        RefreshDomainDataJob::dispatch($domain->id)->afterResponse();

        return redirect()->route('domain.show', $domain->id)
            ->with('success', 'Actualizando datos del dominio en segundo plano. Recarga la página en unos segundos.');
    }

    public function toggleSuspension(Domain $domain): RedirectResponse
    {
        $domain->load('server');
        $shouldSuspend = ! $domain->suspended;

        if ($domain->server && $this->controlPanelManager->supports($domain->server))
        {
            if ($domain->server->control_panel === 'cpanel' && ! $domain->server->hasToken())
            {
                return redirect()->route('domain.show', $domain->id)
                    ->with('error', 'El servidor no tiene credenciales configuradas para suspender la cuenta.');
            }

            if ($domain->server->hasToken())
            {
                $result = $this->controlPanelManager
                    ->forServer($domain->server)
                    ->setAccountSuspended($domain->server, $domain, $shouldSuspend);

                if (! ($result['success'] ?? false))
                {
                    return redirect()->route('domain.show', $domain->id)
                        ->with('error', $result['error'] ?? 'No se pudo actualizar el estado de la cuenta en el servidor.');
                }
            }
        }

        $domain->update([
            'suspended' => $shouldSuspend,
        ]);

        $this->queueDomainRefresh($domain);

        $message = $shouldSuspend
            ? 'Cuenta suspendida correctamente.'
            : 'Cuenta activada correctamente.';

        return redirect()->route('domain.show', $domain->id)
            ->with('success', $message);
    }

    public function changePlan(Request $request, Domain $domain): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => 'required|string|max:255',
        ]);

        $domain->load('server');

        if (! $domain->server || ! $this->controlPanelManager->supports($domain->server))
        {
            return redirect()->route('domain.show', $domain->id)
                ->with('error', 'Para cambiar el plan se requiere un servidor con panel de control configurado.');
        }

        if ($validated['plan'] === $domain->plan)
        {
            return redirect()->route('domain.show', $domain->id)
                ->with('warning', 'El dominio ya tiene asignado ese plan.');
        }

        $availablePlans = $domain->getCachedAvailablePlans();

        if ($availablePlans !== [] && ! in_array($validated['plan'], $availablePlans, true))
        {
            return redirect()->route('domain.show', $domain->id)
                ->with('error', 'El plan seleccionado no está disponible en este servidor.');
        }

        $result = $this->controlPanelManager
            ->forServer($domain->server)
            ->changePlan($domain->server, $domain, $validated['plan']);

        if ($result['success'] ?? false)
        {
            $this->queueDomainRefresh($domain);
        }

        return redirect()->route('domain.show', $domain->id)
            ->with($result['success'] ? 'success' : 'error', $result['success']
                ? 'Plan de hosting actualizado correctamente.'
                : ($result['error'] ?? 'No se pudo actualizar el plan.'));
    }

    public function ensureSpf(Domain $domain): RedirectResponse
    {
        $domain->load('server');

        if (! $domain->server || $domain->server->control_panel !== 'cpanel' || ! $domain->server->hasToken())
        {
            return redirect()->route('domain.show', $domain->id)
                ->with('error', 'La configuración SPF requiere un servidor cPanel con credenciales.');
        }

        $spfRecord = $domain->server->getProvisioningSpfRecord()
            ?: DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT;

        $result = $this->controlPanelManager
            ->forServer($domain->server)
            ->ensureSpfRecord($domain->server, $domain, $spfRecord);

        if ($result['success'] ?? false)
        {
            $this->queueDomainRefresh($domain);
        }

        return redirect()->route('domain.show', $domain->id)
            ->with($result['success'] ? 'success' : 'error', $result['success']
                ? 'Registro SPF configurado correctamente.'
                : ($result['error'] ?? 'No se pudo configurar el registro SPF.'));
    }

    public function updateEmailPassword(UpdateDomainEmailPasswordRequest $request, Domain $domain): RedirectResponse
    {
        $validated = $request->validated();

        $domain->load('server');

        if (! $domain->server || $domain->server->control_panel !== 'cpanel' || ! $domain->server->hasToken())
        {
            return redirect()->route('domain.show', $domain->id)
                ->with('error', 'El servidor no tiene credenciales configuradas para cambiar contraseñas de correo.');
        }

        $result = $this->controlPanelManager
            ->forServer($domain->server)
            ->changeEmailPassword($domain->server, $domain, $validated['email'], $validated['password']);

        if ($result['success'] ?? false)
        {
            $this->queueDomainRefresh($domain);
        }

        return redirect()->route('domain.show', $domain->id)
            ->with($result['success'] ? 'success' : 'error', $result['success']
                ? 'Contraseña de correo actualizada correctamente.'
                : ($result['error'] ?? 'No se pudo actualizar la contraseña de correo.'));
    }

    public function resetCpanelPassword(ResetDomainCpanelPasswordRequest $request, Domain $domain): RedirectResponse
    {
        $validated = $request->validated();

        $result = $this->domainCpanelPasswordService->resetAndOptionallyNotify(
            $request->user(),
            $domain,
            isset($validated['contact_id']) ? (int) $validated['contact_id'] : null,
            (string) ($validated['notify_channel'] ?? 'none'),
            $validated['password'] ?? null,
        );

        if (! ($result['success'] ?? false))
        {
            return redirect()->route('domain.show', $domain->id)
                ->with('error', $result['error'] ?? 'No se pudo actualizar la contraseña de cPanel.');
        }

        $redirect = redirect()->route('domain.show', $domain->id)
            ->with('generated_password', $result['password'] ?? null)
            ->with('cpanel_password_reset', true);

        if (! empty($result['warning']))
        {
            return $redirect->with('warning', $result['warning']);
        }

        if (! empty($result['notified']))
        {
            $channelLabel = ($result['channel'] ?? '') === 'email' ? 'email' : 'WhatsApp';
            $recipient = $result['recipient'] ?? '';

            return $redirect->with(
                'success',
                'Contraseña de cPanel actualizada y enviada por '.$channelLabel
                    .($recipient !== '' ? ' a '.$recipient : '').'.',
            );
        }

        return $redirect->with('success', 'Contraseña de cPanel actualizada correctamente.');
    }

    public function storeEmailAccount(StoreDomainEmailRequest $request, Domain $domain): RedirectResponse
    {
        $validated = $request->validated();

        $domain->load('server');

        if (! $domain->server || $domain->server->control_panel !== 'cpanel' || ! $domain->server->hasToken())
        {
            return redirect()->route('domain.show', $domain->id)
                ->with('error', 'El servidor no tiene credenciales configuradas para crear cuentas de correo.');
        }

        $result = $this->controlPanelManager
            ->forServer($domain->server)
            ->createEmailAccount(
                $domain->server,
                $domain,
                $validated['email'],
                $validated['password'],
            );

        if ($result['success'] ?? false)
        {
            $this->queueDomainRefresh($domain);
        }

        return redirect()->route('domain.show', $domain->id)
            ->with($result['success'] ? 'success' : 'error', $result['success']
                ? 'Cuenta de correo creada correctamente.'
                : ($result['error'] ?? 'No se pudo crear la cuenta de correo.'));
    }

    public function updateMxRecords(Request $request, Domain $domain): RedirectResponse
    {
        $validated = $request->validate([
            'mx_records' => 'required|array|min:1',
            'mx_records.*.priority' => 'required|integer|min:0|max:65535',
            'mx_records.*.target' => 'required|string|max:255',
        ]);

        $domain->load('server');

        if (! $domain->server || $domain->server->control_panel !== 'cpanel' || ! $domain->server->hasToken())
        {
            return redirect()->route('domain.show', $domain->id)
                ->with('error', 'MX record changes require a cPanel server with WHM token.');
        }

        $result = $this->controlPanelManager
            ->forServer($domain->server)
            ->updateMxRecords($domain->server, $domain, $validated['mx_records']);

        if ($result['success'] ?? false)
        {
            $domain->update([
                'data' => array_merge($domain->data ?? [], [
                    'mx_records' => $validated['mx_records'],
                ]),
            ]);

            $this->queueDomainRefresh($domain);
        }

        return redirect()->route('domain.show', $domain->id)
            ->with($result['success'] ? 'success' : 'error', $result['success']
                ? 'MX records updated successfully.'
                : ($result['error'] ?? 'Failed to update MX records.'));
    }

    private function queueDomainRefresh(Domain $domain): void
    {
        RefreshDomainDataJob::dispatch($domain->id)->afterResponse();
    }
}
