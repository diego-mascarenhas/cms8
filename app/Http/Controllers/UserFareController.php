<?php

namespace App\Http\Controllers;

use App\DataTables\UserFareDataTable;
use App\Models\UserFare;
use App\Models\Fare;
use App\Models\Language;
use App\Models\LanguageVariant;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserFareController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(UserFareDataTable $dataTable)
    {
        // Redirigir a la lista de tipos de tarifas
        return redirect()->route('fare.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $fares = Fare::with(['units', 'type'])->get();
        $languages = Language::all();
        $currencies = Currency::all();

        return view('user-fare.form', compact('fares', 'languages', 'currencies'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fare_id' => 'required|exists:fares,id',
            'language_origin_id' => 'required|exists:language_variants,code',
            'language_destination_id' => 'required|exists:language_variants,code',
            'currency_id' => 'required|exists:currencies,code',
            'amount' => 'required|numeric|min:0',
            'negotiable' => 'boolean'
        ]);

        // Agregar el user_id del usuario autenticado
        $validated['user_id'] = Auth::id();

        UserFare::create($validated);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tarifa creada exitosamente'
            ]);
        }

        return redirect()->route('user-fare.index')->with('success', 'Tarifa creada exitosamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(UserFare $userFare)
    {
        $userFare->load(['fare.units', 'fare.type', 'user', 'languageOrigin', 'languageDestination', 'currency']);
        
        return view('user-fare.show', compact('userFare'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UserFare $userFare)
    {
        $fares = Fare::with(['units', 'type'])->get();
        $languages = Language::all();
        $currencies = Currency::all();

        return view('user-fare.form', compact('userFare', 'fares', 'languages', 'currencies'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UserFare $userFare)
    {
        $validated = $request->validate([
            'fare_id' => 'required|exists:fares,id',
            'language_origin_id' => 'required|exists:language_variants,code',
            'language_destination_id' => 'required|exists:language_variants,code',
            'currency_id' => 'required|exists:currencies,code',
            'amount' => 'required|numeric|min:0',
            'negotiable' => 'boolean'
        ]);

        $userFare->update($validated);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tarifa actualizada exitosamente'
            ]);
        }

        return redirect()->route('user-fare.index')->with('success', 'Tarifa actualizada exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UserFare $userFare)
    {
        $userFare->delete();

        return response()->json(['success' => 'Tarifa eliminada exitosamente'], 200);
    }

    /**
     * Display the fare rates available for a collaborator
     */
    public function collaboratorRates($id)
    {
        $collaborator = \App\Models\Contact::findOrFail($id);
        
        // Obtener las tarifas del colaborador con sus precios configurados
        $collaborator->load(['fares.units', 'fares.type']);
        
        // Obtener todas las tarifas disponibles agrupadas por tipo
        $allFares = Fare::with(['units', 'type'])
            ->get()
            ->groupBy('type.name');
        
        // Obtener divisas disponibles
        $currencies = \App\Models\Currency::all();
        
        // Obtener unidades disponibles
        $units = \App\Models\Unit::all();

        return view('collaborator.rates', compact('collaborator', 'allFares', 'currencies', 'units'));
    }

    /**
     * Save the rates configuration for a collaborator
     */
    public function saveCollaboratorRates(Request $request, $id)
    {
        $collaborator = \App\Models\Contact::findOrFail($id);
        
        $validated = $request->validate([
            'currency' => 'required|exists:currencies,code',
            'rates' => 'required|array',
            'rates.*' => 'nullable|numeric|min:0',
            'units' => 'array',
            'units.*' => 'nullable|exists:units,id'
        ]);
        
        $currency = $validated['currency'];
        $rates = $validated['rates'];
        $units = $validated['units'] ?? [];
        
        // Get all fares to process
        $allFares = \App\Models\Fare::all()->keyBy('id');
        
        // Prepare data for sync - only include fares with prices > 0
        $syncData = [];
        
        foreach ($rates as $fareId => $price) {
            if (!empty($price) && $price > 0 && $allFares->has($fareId)) {
                $syncData[$fareId] = [
                    'price' => $price,
                    'currency_code' => $currency,
                    'unit_id' => $units[$fareId] ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }
        
        // Sync the collaborator's fares with their rates
        $collaborator->fares()->sync($syncData);
        
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tarifas actualizadas exitosamente'
            ]);
        }
        
        return redirect()->back()->with('success', 'Tarifas actualizadas exitosamente.');
    }
} 