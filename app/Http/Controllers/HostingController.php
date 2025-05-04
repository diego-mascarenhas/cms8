<?php

namespace App\Http\Controllers;

use App\DataTables\DomainDataTable;
use App\Models\Server;
use App\Models\Domain;
use App\Traits\TracksContactActions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HostingController extends Controller
{
	use TracksContactActions;

	public function index(DomainDataTable $dataTable)
	{
		$data['servers'] = Server::all();

		return $dataTable->render('hosting.index', $data);
	}

	public function create()
	{
		$servers = Server::all();
		return view('hosting.create', compact('servers'));
	}

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
		]);

		// Set default values
		$validated['suspended'] = $request->input('suspended', false);
		$validated['needs_update'] = $request->input('needs_update', false);
		$validated['is_working'] = true;
		$validated['data'] = [];

		$domain = Domain::create($validated);

		return redirect()->route('hosting.index')
			->with('success', 'Hosting creado exitosamente.');
	}

	public function show(Domain $hosting)
	{
		// Redirigir al controlador de dominio para mostrar el dominio
		return redirect()->route('domain.show', $hosting->id);
	}

	public function edit(Domain $hosting)
	{
		$servers = Server::all();
		return view('hosting.create', compact('hosting', 'servers'));
	}

	public function update(Request $request, Domain $hosting)
	{
		$validated = $request->validate([
			'domain' => [
				'required', 
				'string', 
				Rule::unique('domains')->ignore($hosting->id)
			],
			'server_id' => 'required|integer|exists:servers,id',
			'username' => 'required|string',
			'plan' => 'nullable|string',
			'site_type' => 'nullable|string',
			'php_version' => 'nullable|string',
			'notes' => 'nullable|string',
		]);

		// Set boolean values
		$validated['suspended'] = $request->input('suspended', false);
		$validated['needs_update'] = $request->input('needs_update', false);
		$validated['is_working'] = true;

		$hosting->update($validated);

		return redirect()->route('hosting.index')
			->with('success', 'Hosting actualizado exitosamente.');
	}

	public function destroy(Domain $hosting)
	{
		// Redirigir al controlador de dominio para eliminar el dominio
		return redirect()->route('domain.destroy', $hosting->id);
	}
}
