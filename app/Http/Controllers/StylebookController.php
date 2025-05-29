<?php

namespace App\Http\Controllers;

use App\DataTables\StylebookDataTable;
use App\Models\Stylebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StylebookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(StylebookDataTable $dataTable)
    {
        return $dataTable->render('stylebook.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('stylebook.form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'language' => 'required|string|max:2',
            'date' => 'required|date',
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        // Handle file upload
        $filePath = $request->file('file')->store('stylebooks', 'public');

        $stylebook = Stylebook::create([
            'name' => $validated['name'],
            'language' => $validated['language'],
            'date' => $validated['date'],
            'file' => $filePath,
            'team_id' => auth()->user()->currentTeam->id,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Manual de estilo creado exitosamente'
            ]);
        }

        return redirect()->route('stylebook.index')->with('success', 'Manual de estilo creado exitosamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Stylebook $stylebook)
    {
        return view('stylebook.show', compact('stylebook'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Stylebook $stylebook)
    {
        return view('stylebook.form', compact('stylebook'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Stylebook $stylebook)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'language' => 'required|string|max:2',
            'date' => 'required|date',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        $data = [
            'name' => $validated['name'],
            'language' => $validated['language'],
            'date' => $validated['date'],
        ];

        // Handle file upload if a new file is provided
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($stylebook->file && Storage::disk('public')->exists($stylebook->file)) {
                Storage::disk('public')->delete($stylebook->file);
            }
            
            // Store new file
            $filePath = $request->file('file')->store('stylebooks', 'public');
            $data['file'] = $filePath;
        }

        $stylebook->update($data);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Manual de estilo actualizado exitosamente'
            ]);
        }

        return redirect()->route('stylebook.index')->with('success', 'Manual de estilo actualizado exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Stylebook $stylebook)
    {
        // Delete file from storage
        if ($stylebook->file && Storage::disk('public')->exists($stylebook->file)) {
            Storage::disk('public')->delete($stylebook->file);
        }
        
        $stylebook->delete();

        if (request()->ajax()) {
            return response()->json(['success' => true]);
        }
        
        return redirect()->route('stylebook.index')->with('success', 'Manual de estilo eliminado exitosamente');
    }
} 