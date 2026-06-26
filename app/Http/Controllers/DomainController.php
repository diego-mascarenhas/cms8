<?php

namespace App\Http\Controllers;

use App\DataTables\DomainDataTable;
use App\Models\Domain;
use App\Models\Server;
use App\Services\ControlPanel\ControlPanelManager;
use App\Services\WHMService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DomainController extends Controller
{
    public function __construct(
        private ControlPanelManager $controlPanelManager,
        private WHMService $whmService,
    ) {}

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
        $domain->load('server');

        $availablePlans = [];
        $emailAccounts = [];
        $mxRecords = [];
        $controlPanelError = null;
        $controlPanelType = $domain->server?->control_panel;

        if ($domain->server && $this->controlPanelManager->supports($domain->server))
        {
            $connector = $this->controlPanelManager->forServer($domain->server);

            if ($domain->server->control_panel === 'cpanel' && $domain->server->hasToken())
            {
                $plansResult = $connector->listPlans($domain->server);
                $emailsResult = $connector->listEmailAccounts($domain->server, $domain);
                $mxResult = $connector->getMxRecords($domain->server, $domain);

                $availablePlans = $plansResult['plans'] ?? [];
                $emailAccounts = $emailsResult['emails'] ?? [];
                $mxRecords = $mxResult['records'] ?? [];

                if (! ($plansResult['success'] ?? true))
                {
                    $controlPanelError = $plansResult['error'] ?? null;
                } elseif (! ($emailsResult['success'] ?? true))
                {
                    $controlPanelError = $emailsResult['error'] ?? null;
                } elseif (! ($mxResult['success'] ?? true))
                {
                    $controlPanelError = $mxResult['error'] ?? null;
                }
            } elseif ($domain->server->control_panel === 'plesk')
            {
                $controlPanelError = 'Plesk account management will be available soon.';
            } else
            {
                $controlPanelError = 'Configure the server WHM token to manage this account from Humano.';
            }
        }

        return view('domain.show', compact(
            'domain',
            'availablePlans',
            'emailAccounts',
            'mxRecords',
            'controlPanelError',
            'controlPanelType',
        ) + [
            'usesAccountAuth' => (bool) $domain->server?->usesCpanelAccountAuth(),
        ]);
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
        try
        {
            $domain->load('server');

            if (! $domain->server || $domain->server->control_panel !== 'cpanel' || ! $domain->server->hasToken())
            {
                return redirect()->route('domain.show', $domain->id)
                    ->with('warning', 'Refresh requires a cPanel server with WHM token configured.');
            }

            $result = $this->whmService->getDomainsFromServer($domain->server);

            if (! $result['success'])
            {
                return redirect()->route('domain.show', $domain->id)
                    ->with('error', $result['error'] ?? 'Failed to refresh domain data.');
            }

            $account = collect($result['domains'] ?? [])->firstWhere('domain', $domain->domain);

            if (! $account)
            {
                return redirect()->route('domain.show', $domain->id)
                    ->with('warning', 'Account not found on the remote server.');
            }

            $domain->update([
                'username' => $account['user'] ?? $domain->username,
                'plan' => $account['plan'] ?? $domain->plan,
                'suspended' => (bool) ($account['suspended'] ?? false),
                'data' => array_merge($domain->data ?? [], $account, [
                    'last_refreshed' => now()->toIso8601String(),
                ]),
            ]);

            $domain->updatePhpVersion();

            return redirect()->route('domain.show', $domain->id)
                ->with('success', 'Domain data refreshed successfully.');
        } catch (\Exception $e)
        {
            Log::error('Error refreshing domain data: '.$e->getMessage());

            return redirect()->route('domain.show', $domain->id)
                ->with('error', 'Failed to refresh domain data: '.$e->getMessage());
        }
    }

    public function toggleSuspension(Domain $domain): RedirectResponse
    {
        $domain->update([
            'suspended' => ! $domain->suspended,
        ]);

        $status = $domain->suspended ? 'suspended' : 'unsuspended';

        return redirect()->route('domain.show', $domain->id)
            ->with('success', "Domain {$status} successfully.");
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
                ->with('error', 'Plan changes require a configured control panel server.');
        }

        $result = $this->controlPanelManager
            ->forServer($domain->server)
            ->changePlan($domain->server, $domain, $validated['plan']);

        return redirect()->route('domain.show', $domain->id)
            ->with($result['success'] ? 'success' : 'error', $result['success']
                ? 'Hosting plan updated successfully.'
                : ($result['error'] ?? 'Failed to update plan.'));
    }

    public function updateEmailPassword(Request $request, Domain $domain): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8|max:255',
        ]);

        $domain->load('server');

        if (! $domain->server || $domain->server->control_panel !== 'cpanel' || ! $domain->server->hasToken())
        {
            return redirect()->route('domain.show', $domain->id)
                ->with('error', 'Email password changes require a cPanel server with WHM token.');
        }

        $result = $this->controlPanelManager
            ->forServer($domain->server)
            ->changeEmailPassword($domain->server, $domain, $validated['email'], $validated['password']);

        return redirect()->route('domain.show', $domain->id)
            ->with($result['success'] ? 'success' : 'error', $result['success']
                ? 'Email password updated successfully.'
                : ($result['error'] ?? 'Failed to update email password.'));
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

        return redirect()->route('domain.show', $domain->id)
            ->with($result['success'] ? 'success' : 'error', $result['success']
                ? 'MX records updated successfully.'
                : ($result['error'] ?? 'Failed to update MX records.'));
    }
}
