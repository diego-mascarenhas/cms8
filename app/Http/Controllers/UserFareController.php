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
        $fares = Fare::with(['unit', 'block'])->get();
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
        $userFare->load(['fare.unit', 'fare.block', 'user', 'languageOrigin', 'languageDestination', 'currency']);
        
        return view('user-fare.show', compact('userFare'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UserFare $userFare)
    {
        $fares = Fare::with(['unit', 'block'])->get();
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
        
        // Intentar encontrar un usuario asociado con este contacto
        $user = \App\Models\User::where('email', $collaborator->email)->first();
        
        // Obtener las tarifas personalizadas del usuario (si existen)
        if ($user) {
            $userFares = UserFare::where('user_id', $user->id)
                ->with(['fare.unit', 'languageOrigin', 'languageDestination', 'currency'])
                ->get();
        } else {
            $userFares = collect();
        }

        // Obtener las tarifas predefinidas
        $fares = Fare::with(['unit', 'block'])->get();
        
        // Obtener divisas disponibles
        $currencies = \App\Models\Currency::all();
        
        // Obtener variantes de idioma
        $languages = \App\Models\LanguageVariant::all();

        return view('collaborator.rates', compact('collaborator', 'userFares', 'fares', 'currencies', 'languages'));
    }

    /**
     * Save the rates configuration for a collaborator
     */
    public function saveCollaboratorRates(Request $request, $id)
    {
        $collaborator = \App\Models\Contact::findOrFail($id);
        
        // Intentar encontrar un usuario asociado con este contacto
        $user = \App\Models\User::where('email', $collaborator->email)->first();
        
        if (!$user) {
            return redirect()->back()->with('error', 'No se pudo encontrar un usuario asociado a este colaborador.');
        }
        
        // Validar y procesar los datos del formulario
        // Aquí se procesarían todos los campos de tarifas
        
        // Ejemplo de procesamiento
        // foreach ($request->input('rates', []) as $rateType => $value) {
        //     // Buscar o crear la tarifa
        //     UserFare::updateOrCreate(
        //         [
        //             'user_id' => $user->id,
        //             'fare_id' => $rateType,
        //             'language_origin_id' => $request->input('language_origin'),
        //             'language_destination_id' => $request->input('language_destination'),
        //         ],
        //         [
        //             'currency_id' => $request->input('currency'),
        //             'amount' => $value,
        //             'negotiable' => false
        //         ]
        //     );
        // }
        
        return redirect()->back()->with('success', 'Tarifas actualizadas exitosamente.');
    }
} 