<?php

namespace App\Http\Controllers;

use App\DataTables\ProductDataTable;
use App\Models\Product;

class ProductController extends Controller
{
    /**
     * Constructor to authorize product permissions
     */
    public function __construct()
    {
        // Note: Manual authorization in each method due to non-standard route parameter names
        // Laravel's authorizeResource() expects {product} parameter, but routes use {id}
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ProductDataTable $dataTable)
    {
        $this->authorize('viewAny', Product::class);

        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        return $dataTable->render('product.list');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with(['category', 'currency', 'team'])->findOrFail($id);

        $this->authorize('view', $product);

        return view('product.show', compact('product'));
    }
}
