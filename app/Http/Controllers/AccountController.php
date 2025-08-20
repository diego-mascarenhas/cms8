<?php

namespace App\Http\Controllers;

use App\DataTables\AccountDataTable;
use App\Models\Module;
use App\Models\Team;
use Illuminate\Http\Request;

class AccountController extends Controller
{
	public function index(AccountDataTable $dataTable)
	{
		return $dataTable->render('account.index');
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create()
	{
		//
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request)
	{
		//
	}

	/**
	 * Display the specified resource.
	 */
	public function show(string $id)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(string $id)
	{
		$team = Team::findOrFail($id);
		$coreModules = Module::where('is_core', true)->get();
		$additionalModules = Module::where('is_core', false)->get();

		return view('account.form', compact('team', 'coreModules', 'additionalModules'));
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, string $id)
	{
		$team = Team::findOrFail($id);

		$request->validate([
			'name' => 'required|string|max:255',
			'modules' => 'array',
			'modules.*' => 'string|exists:modules,key',
		]);

		$team->update([
			'name' => $request->name,
		]);

		// Get all modules (core and additional)
		$allModules = Module::all();

		// Disable all modules first
		foreach ($allModules as $module)
		{
			$team->disableModule($module->key);
		}

		// Enable selected modules
		if ($request->has('modules'))
		{
			foreach ($request->modules as $moduleKey)
			{
				$team->enableModule($moduleKey);
			}
		}

		return redirect()->route('account-management')
			->with('success', 'Account updated successfully');
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(string $id)
	{
		//
	}
}
