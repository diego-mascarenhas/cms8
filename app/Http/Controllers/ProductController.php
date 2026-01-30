<?php

namespace App\Http\Controllers;

use App\DataTables\ProductDataTable;
use App\Models\Product;
use App\Services\WooCommerceService;

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
     * Display a listing of the resource (local products or WooCommerce products when API is configured).
     */
    public function index(ProductDataTable $dataTable)
    {
        $this->authorize('viewAny', Product::class);

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $woo = new WooCommerceService($team);
        if ($woo->isConfigured())
        {
            $products = $woo->getProducts(1, 100);
            $storeUrl = $woo->getStoreUrl();

            return view('product.woocommerce-list', compact('products', 'storeUrl'));
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
