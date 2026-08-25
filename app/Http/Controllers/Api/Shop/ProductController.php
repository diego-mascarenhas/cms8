<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\FormatsShopResources;
use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShopProductRequest;
use App\Http\Requests\Api\UpdateShopProductRequest;
use App\Http\Requests\ImportProductCsvRequest;
use App\Models\Product;
use App\Models\Store;
use App\Services\ProductCsvImportService;
use App\Services\ProductVariantCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use FormatsShopResources;
    use ResolvesShopTeam;

    public function index(Request $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'store_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'brand_id' => 'nullable|integer',
            'catalog_status' => 'nullable|string|max:32',
            'stock_status' => 'nullable|string|max:32',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Product::query()->with(['category', 'currency', 'store', 'brand', 'options.values', 'variants.optionValues.option']);

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '')
        {
            $query->where(function ($builder) use ($search)
            {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        if (! empty($validated['store_id']))
        {
            $query->where('store_id', $validated['store_id']);
        }

        if (! empty($validated['category_id']))
        {
            $query->where('category_id', $validated['category_id']);
        }

        if (! empty($validated['brand_id']))
        {
            $query->where('brand_id', $validated['brand_id']);
        }

        if (! empty($validated['catalog_status']))
        {
            $query->where('catalog_status', $validated['catalog_status']);
        }

        if (! empty($validated['stock_status']))
        {
            $query->where('stock_status', $validated['stock_status']);
        }

        $paginator = $query->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? 20));

        $items = $paginator->getCollection()
            ->map(fn (Product $product): array => $this->formatProduct($product))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $product = Product::query()->with(['category', 'currency', 'store', 'brand', 'options.values', 'variants.optionValues.option'])->find($id);
        if (! $product)
        {
            return response()->json([
                'success' => false,
                'message' => __('Product not found'),
            ], 404);
        }

        $this->authorize('view', $product);

        return response()->json([
            'success' => true,
            'data' => $this->formatProduct($product),
        ]);
    }

    public function store(StoreShopProductRequest $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        Store::ensureMainStoreForTeam((int) $team->id);

        $validated = $request->validated();
        $product = Product::query()->create(array_merge(
            $this->productPayload($validated),
            ['team_id' => $team->id],
        ));
        app(ProductVariantCatalogService::class)->sync(
            $product,
            app(ProductVariantCatalogService::class)->optionsFromValidated($validated),
            $validated['variants'] ?? null,
        );

        $product->load(['category', 'currency', 'store', 'brand', 'options.values', 'variants.optionValues.option']);

        return response()->json([
            'success' => true,
            'data' => $this->formatProduct($product),
        ], 201);
    }

    public function update(UpdateShopProductRequest $request, int $id): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $product = Product::query()->find($id);
        if (! $product)
        {
            return response()->json([
                'success' => false,
                'message' => __('Product not found'),
            ], 404);
        }

        $validated = $request->validated();
        $product->update($this->productPayload($validated));
        app(ProductVariantCatalogService::class)->sync(
            $product->fresh(),
            app(ProductVariantCatalogService::class)->optionsFromValidated($validated),
            $validated['variants'] ?? null,
        );
        $product->load(['category', 'currency', 'store', 'brand', 'options.values', 'variants.optionValues.option']);

        return response()->json([
            'success' => true,
            'data' => $this->formatProduct($product),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $product = Product::query()->find($id);
        if (! $product)
        {
            return response()->json([
                'success' => false,
                'message' => __('Product not found'),
            ], 404);
        }

        $this->authorize('delete', $product);
        $product->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function importSchema(Request $request, ProductCsvImportService $importer): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('create', Product::class);

        return response()->json([
            'success' => true,
            'data' => [
                'required_columns' => ProductCsvImportService::REQUIRED_COLUMNS,
                'optional_columns' => ProductCsvImportService::OPTIONAL_COLUMNS,
                'sample_csv' => $importer->templateContents(),
                'products_count' => Product::query()->count(),
            ],
        ]);
    }

    public function import(ImportProductCsvRequest $request, ProductCsvImportService $importer): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $result = $importer->import($request->file('file')->getRealPath(), (int) $team->id);
        $imported = $result['created'] + $result['updated'];

        return response()->json([
            'success' => $imported > 0,
            'message' => $imported > 0
                ? __(':created created, :updated updated, :skipped skipped.', [
                    'created' => $result['created'],
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                ])
                : __('No products were imported.'),
            'data' => array_merge($result, [
                'products_count' => Product::query()->count(),
            ]),
        ], $imported > 0 ? 200 : 422);
    }
}
