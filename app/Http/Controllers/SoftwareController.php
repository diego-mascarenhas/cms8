<?php

namespace App\Http\Controllers;

use App\DataTables\SoftwareDataTable;
use App\Models\Software;
use App\Models\SoftwareType;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SoftwareController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SoftwareDataTable $dataTable)
    {
        return $dataTable->render('software.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $types = SoftwareType::all();
        
        return view('software.form', compact('types'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'nullable|exists:software_types,id',
        ]);

        // Add the current team ID
        $validated['team_id'] = auth()->user()->currentTeam->id;

        Software::create($validated);

        return redirect()->route('software.index')
            ->with('success', 'Software creado exitosamente.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Software $software)
    {
        $types = SoftwareType::all();
        
        return view('software.form', compact('software', 'types'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Software $software)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'nullable|exists:software_types,id',
        ]);

        $software->update($validated);

        return redirect()->route('software.index')
            ->with('success', 'Software actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Software $software)
    {
        $software->delete();

        return response()->json(['success' => true]);
    }
} 