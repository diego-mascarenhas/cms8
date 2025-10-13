<?php

namespace App\Http\Controllers;

use App\DataTables\ServerDataTable;
use App\Enums\ServerStatus;
use App\Models\Server;
use App\Services\WhmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    /**
     * Display a listing of servers
     */
    public function index(ServerDataTable $dataTable)
    {
        return $dataTable->render('server.index');
    }

    /**
     * Show the form for creating a new server
     */
    public function create()
    {
        $statuses = ServerStatus::cases();
        $teams = \App\Models\Team::all();

        return view('server.create', compact('statuses', 'teams'));
    }

    /**
     * Store a newly created server
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'ip' => 'nullable|string|ip',
            'server_url' => 'required|string|unique:servers,server_url',
            'username' => 'required|string',
            'operating_system' => 'nullable|string|max:255',
            'control_panel' => 'required|in:none,cpanel,plesk',
            'encrypted_token' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
            'status_id' => 'required|integer',
        ]);

        // Set default values
        $validated['success'] = true;
        $validated['data'] = [];

        $server = Server::create($validated);

        return redirect()->route('server.show', $server->id)
            ->with('success', 'Server created successfully.');
    }

    /**
     * Display the specified server
     */
    public function show(Server $server)
    {
        $cPanelDomains = null;
        $cPanelError = null;

        // If it's a cPanel server, try to get domains from WHM API
        if ($server->control_panel === 'cpanel' && $server->hasToken())
        {
            $whmService = new WhmService;
            $result = $whmService->getDomainsFromServer($server);

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

    /**
     * Show the form for editing the specified server
     */
    public function edit(Server $server)
    {
        $statuses = ServerStatus::cases();
        $teams = \App\Models\Team::all();

        return view('server.edit', compact('server', 'statuses', 'teams'));
    }

    /**
     * Update the specified server
     */
    public function update(Request $request, Server $server)
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
            'encrypted_token' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
            'status_id' => 'required|integer',
        ]);

        $server->update($validated);

        return redirect()->route('server.show', $server->id)
            ->with('success', 'Server updated successfully.');
    }

    /**
     * Remove the specified server
     */
    public function destroy(Server $server)
    {
        // Check if there are any domains using this server
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

    /**
     * Test server connection
     */
    public function testConnection(Server $server)
    {
        try
        {
            // Check if server has required configuration
            if ($server->control_panel !== 'cpanel')
            {
                return redirect()->route('server.show', $server->id)
                    ->with('warning', 'Connection test is only available for cPanel servers.');
            }

            if (! $server->hasToken())
            {
                return redirect()->route('server.show', $server->id)
                    ->with('error', 'Cannot test connection: Server token is not configured.');
            }

            // Test actual API connection using WHM service
            $whmService = new WhmService;

            // Try to get server version first (lightweight test)
            $url = "https://{$server->server_url}:2087";
            $response = Http::withHeaders([
                'Authorization' => $server->getWhmAuthHeader(),
            ])
                ->timeout(30)
                ->get($url.'/json-api/version');

            if ($response->successful())
            {
                $versionData = $response->json();

                // Update server status to success
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

                $message = 'Connection successful! ';
                if (isset($versionData['version']))
                {
                    $message .= "WHM Version: {$versionData['version']}";
                }
                if (isset($versionData['hostname']))
                {
                    $message .= ", Hostname: {$versionData['hostname']}";
                }

                return redirect()->route('server.show', $server->id)
                    ->with('success', $message);
            } else
            {
                // API request failed
                $errorBody = $response->body();
                $statusCode = $response->status();

                $server->update([
                    'success' => false,
                    'status_id' => ServerStatus::Error->value,
                    'data' => array_merge($server->data ?? [], [
                        'last_connection_test' => now()->toIso8601String(),
                        'connection_status' => 'Failed',
                        'error_code' => $statusCode,
                        'error_response' => $errorBody,
                    ]),
                ]);

                $errorMessage = "Connection failed (HTTP {$statusCode})";
                if (str_contains($errorBody, 'authentication failed') || $statusCode === 401)
                {
                    $errorMessage .= ': Invalid credentials or token';
                } elseif (str_contains($errorBody, 'ssl') || str_contains($errorBody, 'certificate'))
                {
                    $errorMessage .= ': SSL/Certificate error';
                } elseif ($statusCode >= 500)
                {
                    $errorMessage .= ': Server error';
                }

                return redirect()->route('server.show', $server->id)
                    ->with('error', $errorMessage);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e)
        {
            // Network/connection errors
            $server->update([
                'success' => false,
                'status_id' => ServerStatus::Error->value,
                'data' => array_merge($server->data ?? [], [
                    'last_connection_test' => now()->toIso8601String(),
                    'connection_status' => 'Connection Failed',
                    'connection_error' => $e->getMessage(),
                ]),
            ]);

            $errorMessage = 'Connection failed: ';
            if (str_contains($e->getMessage(), 'timeout'))
            {
                $errorMessage .= 'Connection timeout - server may be unreachable';
            } elseif (str_contains($e->getMessage(), 'resolve'))
            {
                $errorMessage .= 'Cannot resolve hostname';
            } else
            {
                $errorMessage .= 'Network error';
            }

            return redirect()->route('server.show', $server->id)
                ->with('error', $errorMessage);
        } catch (\Exception $e)
        {
            // Other errors
            Log::error('Error testing server connection: '.$e->getMessage(), [
                'server_id' => $server->id,
                'server_url' => $server->server_url,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $server->update([
                'success' => false,
                'status_id' => ServerStatus::Error->value,
                'data' => array_merge($server->data ?? [], [
                    'last_connection_test' => now()->toIso8601String(),
                    'connection_status' => 'Error',
                    'error_type' => get_class($e),
                    'error_message' => $e->getMessage(),
                ]),
            ]);

            return redirect()->route('server.show', $server->id)
                ->with('error', 'Unexpected error during connection test: '.$e->getMessage());
        }
    }

    /**
     * Sync domains from cPanel server
     */
    public function syncDomains(Server $server)
    {
        if ($server->control_panel !== 'cpanel')
        {
            return response()->json([
                'success' => false,
                'message' => 'Server is not configured for cPanel',
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
            $whmService = new WhmService;
            $result = $whmService->syncDomainsFromServer($server);

            if ($result['success'])
            {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully synced {$result['domains_synced']} domains",
                    'domains_synced' => $result['domains_synced'],
                    'total_domains' => $result['total_domains'],
                ]);
            } else
            {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to sync domains',
                ], 500);
            }
        } catch (\Exception $e)
        {
            Log::error('Error syncing domains: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
