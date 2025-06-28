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
use Illuminate\Support\Facades\DB;

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
        $collaborator->load(['fares.units', 'fares.type', 'languageVariants.sourceLanguage', 'languageVariants.targetLanguage']);
        
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
            'rates' => 'array',
            'rates.*' => 'nullable|numeric|min:0',
            'units' => 'array',
            'units.*' => 'nullable|exists:units,id',
            'same_rates' => 'boolean',
            'current_language_pair' => 'nullable|string',
            'language_rates' => 'array'
        ]);
        
        $currency = $validated['currency'];
        $sameRates = $request->boolean('same_rates', false); // Default to false (different rates per combination)
        
        try {
            DB::beginTransaction();
            
            // First, update ALL existing currency codes for this collaborator to the new currency
            $collaborator->fares()->updateExistingPivot(
                $collaborator->fares->pluck('id')->toArray(),
                ['currency_code' => $currency]
            );
            
            if ($sameRates) {
                // Same rates for all language combinations
                $this->saveSameRatesForAllLanguages($collaborator, $validated, $currency);
            } else {
                // Different rates per language combination
                $this->saveDifferentRatesPerLanguage($collaborator, $validated, $currency);
            }
            
            DB::commit();
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tarifas actualizadas exitosamente'
                ]);
            }
            
            return redirect()->back()->with('success', 'Tarifas actualizadas exitosamente.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar las tarifas: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Error al actualizar las tarifas: ' . $e->getMessage());
        }
    }
    
    /**
     * Save same rates for all language combinations
     */
    private function saveSameRatesForAllLanguages($collaborator, $validated, $currency)
    {
        $rates = $validated['rates'] ?? [];
        $units = $validated['units'] ?? [];
        
        // Get collaborator's language variants
        $languageVariants = $collaborator->languageVariants;
        
        if ($languageVariants->isEmpty()) {
            throw new \Exception('El colaborador no tiene variantes de idiomas configuradas.');
        }
        
        // Delete existing rates for this collaborator
        $collaborator->fares()->detach();
        
        // Create rates for each language combination
        foreach ($languageVariants as $variant) {
            foreach ($rates as $fareId => $price) {
                if (!empty($price) && $price > 0) {
                    $collaborator->fares()->attach($fareId, [
                        'price' => $price,
                        'currency_code' => $currency,
                        'unit_id' => $units[$fareId] ?? null,
                        'source_language_code' => $variant->source_language_code,
                        'target_language_code' => $variant->target_language_code,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }
    
    /**
     * Save different rates per language combination
     */
    private function saveDifferentRatesPerLanguage($collaborator, $validated, $currency)
    {
        $currentRates = $validated['rates'] ?? [];
        $currentUnits = $validated['units'] ?? [];
        $currentLanguagePair = $validated['current_language_pair'] ?? '';
        
        if (!$currentLanguagePair) {
            throw new \Exception('No se especificó la combinación de idiomas actual.');
        }
        
        [$sourceCode, $targetCode] = explode('|', $currentLanguagePair);
        
        // Delete existing rates for ONLY this specific language combination
        $collaborator->fares()
            ->wherePivot('source_language_code', $sourceCode)
            ->wherePivot('target_language_code', $targetCode)
            ->detach();
        
        // Save new rates for this specific language combination
        foreach ($currentRates as $fareId => $price) {
            if (!empty($price) && $price > 0) {
                $collaborator->fares()->attach($fareId, [
                    'price' => $price,
                    'currency_code' => $currency,
                    'unit_id' => $currentUnits[$fareId] ?? null,
                    'source_language_code' => $sourceCode,
                    'target_language_code' => $targetCode,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
        
        // Process any additional language combinations from the form data
        $languageRates = $validated['language_rates'] ?? [];
        foreach ($languageRates as $langPair => $data) {
            // Skip the current language pair as it's already processed above
            if ($langPair === $currentLanguagePair) {
                continue;
            }
            
            $langPairParts = explode('|', $langPair);
            if (count($langPairParts) !== 2) {
                continue; // Skip invalid language pairs
            }
            
            $langSourceCode = $langPairParts[0];
            $langTargetCode = $langPairParts[1];
            $rates = $data['rates'] ?? [];
            $units = $data['units'] ?? [];
            
            // Delete existing rates for this specific language combination
            $collaborator->fares()
                ->wherePivot('source_language_code', $langSourceCode)
                ->wherePivot('target_language_code', $langTargetCode)
                ->detach();
            
            // Save new rates for this language combination
            foreach ($rates as $fareId => $price) {
                if (!empty($price) && $price > 0) {
                    $collaborator->fares()->attach($fareId, [
                        'price' => $price,
                        'currency_code' => $currency,
                        'unit_id' => $units[$fareId] ?? null,
                        'source_language_code' => $langSourceCode,
                        'target_language_code' => $langTargetCode,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }
    
    /**
     * Get rates for a specific language combination
     */
    public function getCollaboratorRates(Request $request, $id)
    {
        $collaborator = \App\Models\Contact::findOrFail($id);
        
        $sourceLanguage = $request->get('source_language');
        $targetLanguage = $request->get('target_language');
        
        if (!$sourceLanguage || !$targetLanguage) {
            return response()->json(['rates' => []]);
        }
        
        // Get rates for this specific language combination
        $rates = $collaborator->fares()
            ->wherePivot('source_language_code', $sourceLanguage)
            ->wherePivot('target_language_code', $targetLanguage)
            ->with('units')
            ->get()
            ->map(function ($fare) {
                return [
                    'fare_id' => $fare->id,
                    'price' => $fare->pivot->price,
                    'unit_id' => $fare->pivot->unit_id,
                    'currency_code' => $fare->pivot->currency_code
                ];
            });
        
        return response()->json(['rates' => $rates]);
    }
} 