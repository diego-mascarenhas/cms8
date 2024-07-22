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

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $hostId = $request->id;

        $data = $request->except(['id', '_token']);

        $request->validate([
            'name' => 'required|string|min:3|max:255',
            'type_id' => 'nullable|exists:host_types,id',
            'user' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'private_ip' => 'nullable|string|unique:hosts,private_ip,' . $hostId,
            'private_connection_id' => 'nullable|exists:network_devices,id',
            'public_ip' => 'nullable|string|unique:hosts,public_ip,' . $hostId,
            'public_connection_id' => 'nullable|exists:network_devices,id',
            'power_state' => 'nullable|string|max:255',
            'connection_state' => 'nullable|string|max:255',
        ]);

        Host::updateOrCreate(
            ['id' => $hostId],
            [
                'name' => $data['name'],
                'type_id' => $data['type_id'] ?? null,
                'user' => $data['user'] ?? null,
                'password' => $data['password'] ?? null,
                'private_ip' => $data['private_ip'] ?? null,
                'private_connection_id' => $data['private_connection_id'] ?? null,
                'public_ip' => $data['public_ip'] ?? null,
                'public_connection_id' => $data['public_connection_id'] ?? null,
                'power_state' => $data['power_state'] ?? null,
                'connection_state' => $data['connection_state'] ?? null,
            ]
        );

        return redirect()->route('host.index')->with('success', 'Host saved successfully.');
    }

    public function edit(string $id)
    {
        $data = Host::find($id);
        $data->types = HostType::getOptions();
        $data->devices = NetworkDevice::getOptions();

        if (!$data)
        {
            return redirect()->route('host.index')->with('error', 'Host not found.');
        }

        return view('host.form', compact('data'));
    }

    public function update(Request $request, Host $host)
    {
        //
    }

    public function destroy(string $id)
    {
        $model = Host::findOrFail($id);

        $model->delete();

        return response()->json(['success' => 'The record has been deleted.'], 200);
    }
}
