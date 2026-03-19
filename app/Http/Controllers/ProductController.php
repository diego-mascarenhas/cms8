<?php

namespace App\Http\Controllers;

use App\DataTables\ProductDataTable;
use App\Http\Requests\StoreLocalProductRequest;
use App\Http\Requests\UpdateLocalProductRequest;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Store;
use App\Services\WordPressService;
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
     * Display a listing of products (Humano catalogue; standalone from WooCommerce).
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

        $wordpress = new WordPressService($team);
        $wordpressConfigured = $wordpress->isConfigured();
        $lastSyncedAt = $wordpressConfigured ? $wordpress->getLastSyncedAt() : null;

        return $dataTable->render('product.list', compact('wordpressConfigured', 'lastSyncedAt'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $currencies = Currency::query()->where('status', true)->orderBy('code')->get();
        $defaultCurrencyId = $currencies->firstWhere('code', 'ARS')?->id ?? $currencies->first()?->id;

        Store::ensureMainStoreForTeam((int) $team->id);
        $stores = Store::query()->where('status', true)->orderByDesc('is_main')->orderBy('name')->get();
        $defaultStoreId = $stores->firstWhere('is_main', true)?->id ?? $stores->first()?->id;

        return view('product.local-form', [
            'product' => null,
            'currencies' => $currencies,
            'stores' => $stores,
            'defaultCurrencyId' => $defaultCurrencyId,
            'defaultStoreId' => $defaultStoreId,
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(string $id): View|RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $product = Product::query()->findOrFail($id);
        $this->authorize('update', $product);

        $currencies = Currency::query()->where('status', true)->orderBy('code')->get();
        $defaultCurrencyId = $product->currency_id;

        Store::ensureMainStoreForTeam((int) $team->id);
        $stores = Store::query()->where('status', true)->orderByDesc('is_main')->orderBy('name')->get();
        $defaultStoreId = $stores->firstWhere('is_main', true)?->id ?? $stores->first()?->id;

        return view('product.local-form', [
            'product' => $product,
            'currencies' => $currencies,
            'stores' => $stores,
            'defaultCurrencyId' => $defaultCurrencyId,
            'defaultStoreId' => $defaultStoreId,
        ]);
    }

    /**
     * Store a newly created product in Humano.
     */
    public function store(StoreLocalProductRequest $request): RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $validated = $request->validated();

        Product::query()->create(array_merge(
            $this->payloadFromValidatedLocalProduct($validated),
            ['team_id' => $team->id],
        ));

        return redirect()->route('product.index')->with('success', __('Product created successfully.'));
    }

    /**
     * Update the specified product in Humano.
     */
    public function update(UpdateLocalProductRequest $request, string $id): RedirectResponse
    {
        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('error-without-team');
        }

        $product = Product::query()->findOrFail($id);

        $validated = $request->validated();

        $product->update($this->payloadFromValidatedLocalProduct($validated));

        return redirect()->route('product.index')->with('success', __('Product updated successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with(['category', 'currency', 'team', 'store'])->findOrFail($id);

        $this->authorize('view', $product);

        return view('product.show', compact('product'));
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $product = Product::query()->findOrFail($id);

        $this->authorize('delete', $product);

        $product->delete();

        return redirect()->route('product.index')->with('success', __('Product deleted successfully.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function payloadFromValidatedLocalProduct(array $validated): array
    {
        $manageStock = (bool) (int) $validated['manage_stock'];

        return [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? '',
            'short_description' => $validated['short_description'] ?? null,
            'price' => $validated['price'],
            'sale_price' => $validated['sale_price'] ?? null,
            'currency_id' => (int) $validated['currency_id'],
            'store_id' => isset($validated['store_id']) && $validated['store_id'] !== '' ? (int) $validated['store_id'] : null,
            'category_id' => (int) $validated['category_id'],
            'catalog_status' => $validated['catalog_status'],
            'stock_status' => $validated['stock_status'],
            'manage_stock' => $manageStock,
            'stock_quantity' => $manageStock ? (int) $validated['stock_quantity'] : null,
            'size_options' => $validated['size_options'] ?? [],
            'color_options' => $validated['color_options'] ?? [],
            'whatsapp_enabled' => (bool) (int) $validated['whatsapp_enabled'],
            'image' => $validated['image'] ?? null,
        ];
    }
}
