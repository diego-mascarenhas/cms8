<?php

namespace App\Http\Controllers;

use App\DataTables\DomainDataTable;
use App\Http\Requests\StoreHostingRequest;
use App\Http\Requests\UpdateHostingRequest;
use App\Models\Category;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Service;
use App\Services\ControlPanel\ControlPanelManager;
use App\Traits\TracksContactActions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HostingController extends Controller
{
    use TracksContactActions;

    public function __construct(
        private ControlPanelManager $controlPanelManager,
    ) {}

    public function index(DomainDataTable $dataTable)
    {
        $domainsQuery = Domain::query();

        if (auth()->user()?->currentTeam)
        {
            $teamId = auth()->user()->currentTeam->id;
            $domainsQuery->whereHas('server', fn ($builder) => $builder->where('team_id', $teamId));
        }

        $total = (clone $domainsQuery)->count();
        $active = (clone $domainsQuery)->where('suspended', false)->count();
        $suspended = (clone $domainsQuery)->where('suspended', true)->count();
        $undefinedPlan = (clone $domainsQuery)->withUndefinedPlan()->count();

        return $dataTable->render('hosting.index', [
            'domainStats' => [
                'total' => $total,
                'active' => $active,
                'suspended' => $suspended,
                'undefined_plan' => $undefinedPlan,
            ],
        ]);
    }

    public function create(Request $request)
    {
        $servers = Server::all();
        $hostingContactEmail = auth()->user()?->currentTeam?->getHostingContactEmail();
        $serviceId = $request->input('service_id');
        $enterpriseId = $request->input('enterprise_id');

        $services = Service::query()
            ->with(['enterprise', 'category'])
            ->when($enterpriseId, fn ($query) => $query->where('enterprise_id', $enterpriseId))
            ->orderByDesc('id')
            ->get();

        return view('hosting.create', compact('servers', 'hostingContactEmail', 'serviceId', 'services'));
    }

    public function store(StoreHostingRequest $request)
    {
        $validated = $request->validated();

        $server = Server::findOrFail($validated['server_id']);
        $generatedPassword = Str::password(16, letters: true, numbers: true, symbols: true);
        $spfConfigured = false;
        $spfError = null;
        $contactEmail = auth()->user()?->currentTeam?->getHostingContactEmail()
            ?? auth()->user()?->email;

        if ($server->control_panel === 'cpanel')
        {
            if (! $server->hasToken())
            {
                return back()
                    ->withInput()
                    ->withErrors(['provision' => 'El servidor no tiene credenciales configuradas para crear la cuenta.']);
            }

            $connector = $this->controlPanelManager->forServer($server);

            $result = $connector->createAccount(
                $server,
                $validated['username'],
                $validated['domain'],
                $validated['plan'],
                $generatedPassword,
                $contactEmail,
            );

            if (! $result['success'])
            {
                return back()
                    ->withInput()
                    ->withErrors(['provision' => $result['error'] ?? 'No se pudo crear la cuenta en cPanel.']);
            }
        }

        $validated['suspended'] = false;
        $validated['needs_update'] = false;
        $validated['is_working'] = true;
        $validated['data'] = [];
        $validated['service_id'] = $this->resolveServiceIdForHosting($validated, $server);
        unset($validated['enterprise_id']);

        $domain = Domain::create($validated);

        if ($server->control_panel === 'cpanel' && $server->hasToken())
        {
            $spfRecord = $server->getProvisioningSpfRecord();

            if ($spfRecord !== '')
            {
                $spfResult = $this->controlPanelManager->forServer($server)->ensureSpfRecord(
                    $server,
                    $domain,
                    $spfRecord,
                );

                $spfConfigured = (bool) ($spfResult['success'] ?? false);
                $spfError = $spfResult['error'] ?? null;
            }
        }

        return redirect()->route('domain.show', $domain->id)
            ->with('success', 'Hosting creado exitosamente.')
            ->with('hosting_provisioned', true)
            ->with('generated_password', $generatedPassword)
            ->with('dns_nameservers', $server->getProvisioningNameservers())
            ->with('spf_configured', $spfConfigured)
            ->with('spf_error', $spfError);
    }

    public function show(Domain $hosting)
    {
        return redirect()->route('domain.show', $hosting->id);
    }

    public function edit(Domain $hosting)
    {
        $servers = Server::all();
        $services = Service::query()
            ->with(['enterprise', 'category'])
            ->orderByDesc('id')
            ->get();

        return view('hosting.create', compact('hosting', 'servers', 'services'));
    }

    public function update(UpdateHostingRequest $request, Domain $hosting)
    {
        $validated = $request->validated();

        $validated['needs_update'] = $request->boolean('needs_update');
        $validated['is_working'] = true;

        $hosting->update($validated);

        return redirect()->route('hosting.index')
            ->with('success', 'Hosting actualizado exitosamente.');
    }

    public function destroy(Domain $hosting)
    {
        return redirect()->route('domain.destroy', $hosting->id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveServiceIdForHosting(array $validated, Server $server): ?int
    {
        if (! empty($validated['service_id']))
        {
            $service = Service::query()->findOrFail($validated['service_id']);
            $this->syncServiceHostingData($service, $validated, $server);

            return $service->id;
        }

        if (empty($validated['enterprise_id']))
        {
            return null;
        }

        $categoryId = Category::query()
            ->where('name', 'like', '%Hosting%')
            ->value('id') ?? Category::query()->value('id');

        if ($categoryId === null)
        {
            return null;
        }

        $service = Service::create([
            'enterprise_id' => $validated['enterprise_id'],
            'category_id' => $categoryId,
            'operation' => 'sell',
            'description' => 'Hosting '.$validated['domain'],
            'data' => $this->buildServiceHostingData($validated, $server),
            'responsible_id' => auth()->id(),
            'status' => 4,
        ]);

        return $service->id;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildServiceHostingData(array $validated, Server $server): array
    {
        return [
            'domain' => $validated['domain'],
            'username' => $validated['username'],
            'plan' => $validated['plan'],
            'server_id' => $server->id,
            'server_url' => $server->server_url,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncServiceHostingData(Service $service, array $validated, Server $server): void
    {
        $service->update([
            'data' => array_merge($service->data ?? [], $this->buildServiceHostingData($validated, $server)),
        ]);
    }
}
