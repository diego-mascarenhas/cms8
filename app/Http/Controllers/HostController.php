<?php

namespace App\Http\Controllers;

use App\DataTables\HostDataTable;
use App\Models\Host;
use App\Models\HostType;
use App\Models\NetworkDevice;
use Illuminate\Http\Request;

class HostController extends Controller
{
    public function index(HostDataTable $dataTable)
    {
        return $dataTable->render('host.index');
    }

    public function indexX()
    {
        $hosts = Host::with(['hostType', 'hostConnection', 'publicConnection'])->get();
        return view('hosts.index', compact('hosts'));
    }

    public function create()
    {
        $hostTypes = HostType::all();
        $networkDevices = NetworkDevice::all();
        return view('hosts.create', compact('hostTypes', 'networkDevices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255|unique:hosts,host',
            'user' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'public_ip' => 'nullable|string|max:255|unique:hosts,public_ip',
            'data' => 'nullable|json',
            'power_state' => 'nullable|string|max:255',
            'connection_state' => 'nullable|string|max:255',
            'type_id' => 'nullable|exists:host_types,id',
            'private_connection_id' => 'nullable|exists:network_devices,id',
            'public_connection_id' => 'nullable|exists:network_devices,id',
        ]);

        Host::create($request->all());
        return redirect()->route('hosts.index')->with('success', 'Host created successfully.');
    }

    public function edit(Host $host)
    {
        $hostTypes = HostType::all();
        $networkDevices = NetworkDevice::all();
        return view('hosts.edit', compact('host', 'hostTypes', 'networkDevices'));
    }

    public function update(Request $request, Host $host)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255|unique:hosts,host,' . $host->id,
            'user' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'public_ip' => 'nullable|string|max:255|unique:hosts,public_ip,' . $host->id,
            'data' => 'nullable|json',
            'power_state' => 'nullable|string|max:255',
            'connection_state' => 'nullable|string|max:255',
            'type_id' => 'nullable|exists:host_types,id',
            'private_connection_id' => 'nullable|exists:network_devices,id',
            'public_connection_id' => 'nullable|exists:network_devices,id',
        ]);

        $host->update($request->all());
        return redirect()->route('hosts.index')->with('success', 'Host updated successfully.');
    }

    public function destroy(Host $host)
    {
        $host->delete();
        return redirect()->route('hosts.index')->with('success', 'Host deleted successfully.');
    }
}
