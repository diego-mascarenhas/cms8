<?php

namespace App\Http\Controllers;

use App\DataTables\FareDataTable;
use App\Models\Fare;
use App\Models\Unit;
use App\Models\FareBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FareController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $fares = Fare::with(['unit', 'block'])->get();
        return view('fare.index', compact('fares'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $units = Unit::all();
        $blocks = FareBlock::all();

        return view('fare.form', compact('units', 'blocks'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit_id' => 'required|exists:units,id',
            'block_id' => 'nullable|exists:fares_blocks,id',
            'glosary_id' => 'nullable|exists:glosaries,id',
        ]);

        Fare::create($validated);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tarifa creada exitosamente'
            ]);
        }

        return redirect()->route('fare.index')->with('success', 'Tarifa creada exitosamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Fare $fare)
    {
        $fare->load(['unit', 'block']);
        
        return view('fare.show', compact('fare'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Fare $fare)
    {
        $units = Unit::all();
        $blocks = FareBlock::all();

        return view('fare.form', compact('fare', 'units', 'blocks'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Fare $fare)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit_id' => 'required|exists:units,id',
            'block_id' => 'nullable|exists:fares_blocks,id',
            'glosary_id' => 'nullable|exists:glosaries,id',
        ]);

        $fare->update($validated);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tarifa actualizada exitosamente'
            ]);
        }

        return redirect()->route('fare.index')->with('success', 'Tarifa actualizada exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Fare $fare)
    {
        $fare->delete();

        return response()->json(['success' => 'Tarifa eliminada exitosamente'], 200);
    }
} 