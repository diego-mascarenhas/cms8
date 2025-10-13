<?php

namespace App\Http\Controllers;

use App\DataTables\CustomerFareDataTable;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\CustomerFare;
use App\Models\Fare;
use App\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerFareController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CustomerFareDataTable $dataTable)
    {
        return $dataTable->render('customer-fare.index');
    }

    /**
     * Display rates for a specific collaborator
     */
    public function collaboratorRates($collaboratorId, CustomerFareDataTable $dataTable)
    {
        $collaborator = Contact::findOrFail($collaboratorId);

        return $dataTable->with('customer_id', $collaboratorId)
            ->render('collaborator.rates-datatable', compact('collaborator'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $fares = Fare::with(['unit', 'block'])->get();
        $languages = Language::all();
        $currencies = Currency::all();

        return view('customer-fare.form', compact('fares', 'languages', 'currencies'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:users,id',
            'language_origin_id' => 'required|exists:language_variants,code',
            'language_destination_id' => 'required|exists:language_variants,code',
            'fare_id' => 'required|exists:fares,id',
            'currency_id' => 'required|exists:currencies,code',
            'amount' => 'required|numeric|min:0',
            'negotiable' => 'boolean',
        ]);

        // Find or create contact for this user
        $user = User::findOrFail($validated['customer_id']);
        $contact = Contact::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $user->name,
                'team_id' => auth()->user()->currentTeam->id,
                'creator_id' => auth()->id(),
            ],
        );

        // Replace user_id with contact_id
        $validated['customer_id'] = $contact->id;

        CustomerFare::create($validated);

        if ($request->ajax())
        {
            return response()->json([
                'success' => true,
                'message' => 'Tarifa creada exitosamente',
            ]);
        }

        return redirect()->route('customer-fare.index')->with('success', 'Tarifa creada exitosamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerFare $customerFare)
    {
        $customerFare->load(['fare.unit', 'fare.block', 'customer', 'languageOrigin', 'languageDestination', 'currency']);

        return view('customer-fare.show', compact('customerFare'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CustomerFare $customerFare)
    {
        $fares = Fare::with(['unit', 'block'])->get();
        $languages = Language::all();
        $currencies = Currency::all();

        // Get the user_id for the selected contact
        if ($customerFare->customer && $customerFare->customer->user_id)
        {
            $customerFare->customer_id = $customerFare->customer->user_id;
        }

        return view('customer-fare.form', compact('customerFare', 'fares', 'languages', 'currencies'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CustomerFare $customerFare)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:users,id',
            'language_origin_id' => 'required|exists:language_variants,code',
            'language_destination_id' => 'required|exists:language_variants,code',
            'fare_id' => 'required|exists:fares,id',
            'currency_id' => 'required|exists:currencies,code',
            'amount' => 'required|numeric|min:0',
            'negotiable' => 'boolean',
        ]);

        // Find or create contact for this user
        $user = User::findOrFail($validated['customer_id']);
        $contact = Contact::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $user->name,
                'team_id' => auth()->user()->currentTeam->id,
                'creator_id' => auth()->id(),
            ],
        );

        // Replace user_id with contact_id
        $validated['customer_id'] = $contact->id;

        $customerFare->update($validated);

        if ($request->ajax())
        {
            return response()->json([
                'success' => true,
                'message' => 'Tarifa actualizada exitosamente',
            ]);
        }

        return redirect()->route('customer-fare.index')->with('success', 'Tarifa actualizada exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerFare $customerFare)
    {
        $customerFare->delete();

        return response()->json(['success' => 'Tarifa eliminada exitosamente'], 200);
    }
}
