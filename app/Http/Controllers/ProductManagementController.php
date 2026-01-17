<?php

namespace App\Http\Controllers;

use App\DataTables\ProductDataTable;
use App\Models\SubscriptionProduct;
use Illuminate\Http\Request;

class ProductManagementController extends Controller
{
    /**
     * Display a listing of subscription products.
     */
    public function index(ProductDataTable $dataTable)
    {
        return $dataTable->render('account.products.index');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(string $id)
    {
        $product = SubscriptionProduct::findOrFail($id);

        return view('account.products.edit', compact('product'));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = SubscriptionProduct::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'active' => 'boolean',
            'category' => 'nullable|string|max:255',
            'plan' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'currency' => 'required|string|size:3',
            'unit_amount' => 'nullable|numeric|min:0',
            'recurring_interval' => 'nullable|string|in:day,week,month,year',
            'recurring_interval_count' => 'nullable|integer|min:1',
        ]);

        // Convert unit_amount from cents to dollars (if provided)
        if (isset($validated['unit_amount']) && $validated['unit_amount'] > 0)
        {
            $validated['unit_amount'] = $validated['unit_amount'] / 100;
        }

        $product->update($validated);

        return redirect()
            ->route('account.products.index')
            ->with('success', 'Producto actualizado exitosamente');
    }
}
