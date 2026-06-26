<?php

namespace App\Http\Controllers;

use App\DataTables\ServerDataTable;
use App\Enums\ServerStatus;
use App\Models\Server;
use App\Services\ControlPanel\ControlPanelManager;
use App\Services\ControlPanel\CpanelConnector;
use App\Services\WHMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServerController extends Controller
{
    public function __construct(
        private ControlPanelManager $controlPanelManager,
        private WHMService $whmService,
        private CpanelConnector $cpanelConnector,
    ) {}

    public function index(ServerDataTable $dataTable)
    {
        return $dataTable->render('server.index');
    }

    public function create(): View
    {
        $statuses = ServerStatus::cases();
        $teams = \App\Models\Team::all();
        $data = new Server;

        return view('server.form', compact('statuses', 'teams', 'data'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'ip' => 'nullable|string|ip',
            'server_url' => 'required|string|unique:servers,server_url',
            'username' => 'required|string',
            'operating_system' => 'nullable|string|max:255',
            'control_panel' => 'required|in:none,cpanel,plesk',
            'auth_mode' => 'nullable|in:whm,cpanel_user',
            'encrypted_token' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
            'status_id' => 'required|integer',
        ]);

        $validated['success'] = false;
        $validated['data'] = $this->buildServerData($request, []);
        $validated['team_id'] = $validated['team_id'] ?? auth()->user()?->currentTeam?->id;

        $server = Server::create($validated);

        return redirect()->route('server.show', $server->id)
            ->with('success', 'Server created successfully.');
    }

    public function show(Server $server): View
    {
        $cPanelDomains = null;
        $cPanelError = null;

        if ($server->control_panel === 'cpanel' && $server->hasToken())
        {
            $result = $this->whmService->getDomainsFromServer($server);

            if ($result['success'])
            {
                $cPanelDomains = $result['domains'];
            } else
            {
                $cPanelError = $result['error'];
            }
        }

        return view('server.show', compact('server', 'cPanelDomains', 'cPanelError'));
    }

    public function edit(Server $server): View
    {
        $statuses = ServerStatus::cases();
        $teams = \App\Models\Team::all();
        $data = $server;

        return view('server.form', compact('server', 'statuses', 'teams', 'data'));
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'ip' => 'nullable|string|ip',
            'server_url' => [
                'required',
                'string',
                Rule::unique('servers')->ignore($server->id),
            ],
            'username' => 'required|string',
            'operating_system' => 'nullable|string|max:255',
            'control_panel' => 'required|in:none,cpanel,plesk',
            'auth_mode' => 'nullable|in:whm,cpanel_user',
            'encrypted_token' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
            'status_id' => 'required|integer',
        ]);

        if (empty($validated['encrypted_token']))
        {
            unset($validated['encrypted_token']);
        }

        $validated['data'] = $this->buildServerData($request, $server->data ?? []);

        $server->update($validated);

        return redirect()->route('server.show', $server->id)
            ->with('success', 'Server updated successfully.');
    }

    public function destroy(Server $server): RedirectResponse
    {
        $domainCount = $server->domains()->count();

        if ($domainCount > 0)
        {
            return redirect()->route('server.show', $server->id)
                ->with('error', "Cannot delete server: {$domainCount} domains are using this server.");
        }

        $server->delete();

        return redirect()->route('server.index')
            ->with('success', 'Server deleted successfully.');
    }

    public function testConnection(Server $server): RedirectResponse
    {
        try
        {
            if (! $this->controlPanelManager->supports($server))
            {
                return redirect()->route('server.show', $server->id)
                    ->with('warning', 'Connection test is only available for cPanel and Plesk servers.');
            }

            if ($server->control_panel === 'plesk')
            {
                $result = $this->controlPanelManager->forServer($server)->testConnection($server);

                return redirect()->route('server.show', $server->id)
                    ->with($result['success'] ? 'success' : 'warning', $result['error'] ?? 'Plesk connection test completed.');
            }

            if (! $server->hasToken())
            {
                return redirect()->route('server.show', $server->id)
                    ->with('error', 'Cannot test connection: credentials are not configured.');
            }

            if ($server->usesCpanelAccountAuth())
            {
                $result = $this->cpanelConnector->testConnection($server);

                $server->update([
                    'success' => $result['success'],
                    'status_id' => $result['success'] ? ServerStatus::Active->value : ServerStatus::Error->value,
                    'data' => array_merge($server->data ?? [], [
                        'last_connection_test' => now()->toIso8601String(),
                        'connection_status' => $result['success'] ? 'Success' : 'Failed',
                        'account_domain' => $result['domain'] ?? null,
                        'account_plan' => $result['plan'] ?? null,
                    ]),
                ]);

                $message = $result['success']
                    ? 'cPanel account connected. Domain: '.($result['domain'] ?? 'unknown')
                    : ($result['error'] ?? 'Connection failed');

                return redirect()->route('server.show', $server->id)
                    ->with($result['success'] ? 'success' : 'error', $message);
            }

            $url = "https://{$server->server_url}:2087";
            $response = Http::withHeaders([
                'Authorization' => $server->getWhmAuthHeader(),
            ])
                ->timeout(30)
                ->get($url.'/json-api/version');

            if ($response->successful())
            {
                $versionData = $response->json();

                $server->update([
                    'success' => true,
                    'status_id' => ServerStatus::Active->value,
                    'data' => array_merge($server->data ?? [], [
                        'last_connection_test' => now()->toIso8601String(),
                        'connection_status' => 'Success',
                        'api_version' => $versionData['version'] ?? null,
                        'build' => $versionData['build'] ?? null,
                        'server_hostname' => $versionData['hostname'] ?? null,
                        'test_response_time' => $response->transferStats ?
                            round($response->transferStats->getTransferTime() * 1000, 2).'ms' : null,
                    ]),
                ]);

                $message = 'Connection successful!';
                if (isset($versionData['version']))
                {
                    $message .= " WHM Version: {$versionData['version']}";
                }

                return redirect()->route('server.show', $server->id)
                    ->with('success', $message);
            }

            $server->update([
                'success' => false,
                'status_id' => ServerStatus::Error->value,
                'data' => array_merge($server->data ?? [], [
                    'last_connection_test' => now()->toIso8601String(),
                    'connection_status' => 'Failed',
                    'error_code' => $response->status(),
                    'error_response' => $response->body(),
                ]),
            ]);

            return redirect()->route('server.show', $server->id)
                ->with('error', 'Connection failed (HTTP '.$response->status().')');
        } catch (\Illuminate\Http\Client\ConnectionException $e)
        {
            $server->update([
                'success' => false,
                'status_id' => ServerStatus::Error->value,
                'data' => array_merge($server->data ?? [], [
                    'last_connection_test' => now()->toIso8601String(),
                    'connection_status' => 'Connection Failed',
                    'connection_error' => $e->getMessage(),
                ]),
            ]);

            return redirect()->route('server.show', $server->id)
                ->with('error', 'Connection failed: Network error');
        } catch (\Exception $e)
        {
            Log::error('Error testing server connection: '.$e->getMessage(), [
                'server_id' => $server->id,
            ]);

            return redirect()->route('server.show', $server->id)
                ->with('error', 'Unexpected error during connection test: '.$e->getMessage());
        }
    }

    public function syncDomains(Server $server): JsonResponse
    {
        if ($server->control_panel !== 'cpanel')
        {
            return response()->json([
                'success' => false,
                'message' => 'Domain sync is only available for cPanel servers',
            ], 400);
        }

        if (! $server->hasToken())
        {
            return response()->json([
                'success' => false,
                'message' => 'Server token is not configured',
            ], 400);
        }

        try
        {
            $result = $this->whmService->syncDomainsFromServer($server);

            if ($result['success'])
            {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully synced {$result['domains_synced']} domains",
                    'domains_synced' => $result['domains_synced'],
                    'total_domains' => $result['total_domains'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to sync domains',
            ], 500);
        } catch (\Exception $e)
        {
            Log::error('Error syncing domains: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function buildServerData(Request $request, array $existing): array
    {
        $data = $existing;

        if ($request->input('control_panel') === 'cpanel')
        {
            $data['auth_mode'] = $request->input('auth_mode', $existing['auth_mode'] ?? 'whm');
        } else
        {
            unset($data['auth_mode']);
        }

        return $data;
    }
}
