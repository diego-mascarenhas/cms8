<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Server;
use Illuminate\Http\Request;
use App\DataTables\DomainDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DomainController extends Controller
{
    /**
     * Display a listing of domains
     */
    public function index(DomainDataTable $dataTable)
    {
        $servers = Server::all();
        return $dataTable->render('domain.index', compact('servers'));
    }

    /**
     * Show the form for creating a new domain
     */
    public function create()
    {
        $servers = Server::all();
        return view('domain.create', compact('servers'));
    }

    /**
     * Store a newly created domain
     */
    public function store(Request $request)
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

        // Set default values
        $validated['suspended'] = $request->input('suspended', false);
        $validated['needs_update'] = $request->input('needs_update', false);
        $validated['is_working'] = $request->input('is_working', true);
        $validated['data'] = [];

        $domain = Domain::create($validated);

        return redirect()->route('domain.show', $domain->id)
            ->with('success', 'Domain created successfully.');
    }

    /**
     * Display the specified domain
     */
    public function show(Domain $domain)
    {
        return view('domain.show', compact('domain'));
    }

    /**
     * Show the form for editing the specified domain
     */
    public function edit(Domain $domain)
    {
        $servers = Server::all();
        return view('domain.edit', compact('domain', 'servers'));
    }

    /**
     * Update the specified domain
     */
    public function update(Request $request, Domain $domain)
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

        // Set default values with consistent handling
        $validated['suspended'] = $request->input('suspended', false);
        $validated['needs_update'] = $request->input('needs_update', false);
        $validated['is_working'] = $request->input('is_working', true);

        $domain->update($validated);

        return redirect()->route('domain.show', $domain->id)
            ->with('success', 'Domain updated successfully.');
    }

    /**
     * Remove the specified domain
     */
    public function destroy(Domain $domain)
    {
        $domain->delete();
        return redirect()->route('domain.index')
            ->with('success', 'Domain deleted successfully.');
    }

    /**
     * Fetch domain data from WHM/cPanel
     */
    public function refresh(Domain $domain)
    {
        try {
            // Logic to connect to WHM/cPanel API and refresh domain data
            // This is a placeholder for the actual implementation
            
            // Example: Update some domain data
            $domain->update([
                'data' => array_merge($domain->data ?? [], [
                    'last_refreshed' => now()->toIso8601String()
                ])
            ]);
            
            return redirect()->route('domain.show', $domain->id)
                ->with('success', 'Domain data refreshed successfully.');
        } catch (\Exception $e) {
            Log::error('Error refreshing domain data: ' . $e->getMessage());
            return redirect()->route('domain.show', $domain->id)
                ->with('error', 'Failed to refresh domain data: ' . $e->getMessage());
        }
    }

    /**
     * Toggle domain suspension status
     */
    public function toggleSuspension(Domain $domain)
    {
        $domain->update([
            'suspended' => !$domain->suspended
        ]);

        $status = $domain->suspended ? 'suspended' : 'unsuspended';
        return redirect()->route('domain.show', $domain->id)
            ->with('success', "Domain {$status} successfully.");
    }
} 