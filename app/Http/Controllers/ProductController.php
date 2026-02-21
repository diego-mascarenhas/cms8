<?php

namespace App\Http\Controllers;

use App\DataTables\ProductDataTable;
use App\Http\Requests\StoreWooCommerceProductRequest;
use App\Http\Requests\UpdateWooCommerceProductRequest;
use App\Models\Product;
use App\Services\WooCommerceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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

        $user = auth()->user();
        $team = $user->currentTeam ?? null;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $woo = new WooCommerceService($team);
        $wooConfigured = $woo->isConfigured();
        if ($wooConfigured)
        {
            $products = $woo->getProducts(1, 100);
            $storeUrl = $woo->getStoreUrl();

            return view('product.woocommerce-list', compact('products', 'storeUrl'));
        }

        return $dataTable->render('product.list');
    }

    /**
     * Show the form for creating a new resource (WooCommerce product when API is configured).
     */
    public function create(): View|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $woo = new WooCommerceService($team);
        if (! $woo->isConfigured())
        {
            return redirect()->route('product.index')->with('error', __('WooCommerce is not configured for this team.'));
        }

        return view('product.woocommerce-form', ['product' => null, 'storeUrl' => $woo->getStoreUrl()]);
    }

    /**
     * Show the form for editing the specified resource (WooCommerce product when API is configured).
     */
    public function edit(string $id): View|RedirectResponse
    {
        $this->authorize('update', new Product(['team_id' => auth()->user()->currentTeam->id]));

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $woo = new WooCommerceService($team);
        if (! $woo->isConfigured())
        {
            return redirect()->route('product.index')->with('error', __('WooCommerce is not configured for this team.'));
        }

        $product = $woo->getProduct((int) $id);
        if (! $product)
        {
            return redirect()->route('product.index')->with('error', __('Product not found.'));
        }

        return view('product.woocommerce-form', ['product' => $product, 'storeUrl' => $woo->getStoreUrl()]);
    }

    /**
     * Store a newly created WooCommerce product via API.
     */
    public function store(StoreWooCommerceProductRequest $request): RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $woo = new WooCommerceService($team);
        if (! $woo->isConfigured())
        {
            return redirect()->route('product.index')->with('error', __('WooCommerce is not configured for this team.'));
        }

        $data = $this->productRequestToApiPayload($request->validated());
        $created = $woo->createProduct($data);

        if (! $created)
        {
            return back()->withInput()->with('error', __('Failed to create product in WooCommerce.'));
        }

        return redirect()->route('product.index')->with('success', __('Product created successfully.'));
    }

    /**
     * Update the specified WooCommerce product via API.
     */
    public function update(UpdateWooCommerceProductRequest $request, string $id): RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $woo = new WooCommerceService($team);
        if (! $woo->isConfigured())
        {
            return redirect()->route('product.index')->with('error', __('WooCommerce is not configured for this team.'));
        }

        $data = $this->productRequestToApiPayload($request->validated());
        $updated = $woo->updateProduct((int) $id, $data);

        if (! $updated)
        {
            return back()->withInput()->with('error', __('Failed to update product in WooCommerce.'));
        }

        return redirect()->route('product.index')->with('success', __('Product updated successfully.'));
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function productRequestToApiPayload(array $validated): array
    {
        $payload = [
            'name' => $validated['name'],
            'type' => 'simple',
        ];

        if (isset($validated['regular_price']))
        {
            $payload['regular_price'] = (string) $validated['regular_price'];
        }
        if (isset($validated['sale_price']))
        {
            $payload['sale_price'] = (string) $validated['sale_price'];
        }
        if (array_key_exists('description', $validated))
        {
            $payload['description'] = (string) ($validated['description'] ?? '');
        }
        if (array_key_exists('short_description', $validated))
        {
            $payload['short_description'] = (string) ($validated['short_description'] ?? '');
        }
        if (isset($validated['status']))
        {
            $payload['status'] = $validated['status'];
        }
        if (isset($validated['stock_status']))
        {
            $payload['stock_status'] = $validated['stock_status'];
        }
        if (isset($validated['manage_stock']))
        {
            $payload['manage_stock'] = (bool) $validated['manage_stock'];
        }
        if (isset($validated['stock_quantity']))
        {
            $payload['stock_quantity'] = (int) $validated['stock_quantity'];
        }

        return $payload;
    }
}
