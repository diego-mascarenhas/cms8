<?php

namespace App\Http\Controllers;

use App\DataTables\CertificationDataTable;
use App\Models\Certification;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Certification::class, 'certification');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(CertificationDataTable $dataTable)
    {
        return $dataTable->render('certification.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('certification.form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'certification' => 'required|string|max:255',
            'language' => 'required|string|max:2',
        ]);

        $certification = Certification::create([
            'certification' => $validated['certification'],
            'language' => $validated['language'],
            'team_id' => auth()->user()->currentTeam->id,
        ]);

        if ($request->ajax())
        {
            return response()->json([
                'success' => true,
                'message' => 'Certificación creada exitosamente',
            ]);
        }

        return redirect()->route('certification.index')->with('success', 'Certificación creada exitosamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Certification $certification)
    {
        return view('certification.show', compact('certification'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Certification $certification)
    {
        return view('certification.form', compact('certification'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Certification $certification)
    {
        $validated = $request->validate([
            'certification' => 'required|string|max:255',
            'language' => 'required|string|max:2',
        ]);

        $certification->update([
            'certification' => $validated['certification'],
            'language' => $validated['language'],
            'team_id' => auth()->user()->currentTeam->id,
        ]);

        if ($request->ajax())
        {
            return response()->json([
                'success' => true,
                'message' => 'Certificación actualizada exitosamente',
            ]);
        }

        return redirect()->route('certification.index')->with('success', 'Certificación actualizada exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Certification $certification)
    {
        $certification->delete();

        if (request()->ajax())
        {
            return response()->json(['success' => true]);
        }

        return redirect()->route('certification.index')->with('success', 'Certificación eliminada exitosamente');
    }
}
