<?php

namespace App\Http\Controllers;

use App\DataTables\ServerDataTable;
use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Http\Request;
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
        return view('server.show', compact('server'));
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

        if ($domainCount > 0) {
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
        try {
            // Logic to test connection to the server
            // This is a placeholder for the actual implementation

            $success = true; // Set based on connection test result

            $server->update([
                'success' => $success,
                'status_id' => $success ? ServerStatus::Active->value : ServerStatus::Error->value,
                'data' => array_merge($server->data ?? [], [
                    'last_connection_test' => now()->toIso8601String(),
                    'connection_status' => $success ? 'Success' : 'Failed',
                ]),
            ]);

            $message = $success ? 'Connection successful' : 'Connection failed';
            $type = $success ? 'success' : 'error';

            return redirect()->route('server.show', $server->id)
                ->with($type, $message);

        } catch (\Exception $e) {
            Log::error('Error testing server connection: '.$e->getMessage());

            $server->update([
                'status_id' => ServerStatus::Error->value,
                'data' => array_merge($server->data ?? [], [
                    'last_connection_test' => now()->toIso8601String(),
                    'connection_error' => $e->getMessage(),
                ]),
            ]);

            return redirect()->route('server.show', $server->id)
                ->with('error', 'Failed to test connection: '.$e->getMessage());
        }
    }
}
