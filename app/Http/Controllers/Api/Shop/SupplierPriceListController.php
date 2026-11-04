<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportSupplierPriceListRequest;
use App\Http\Requests\Api\StoreShopSupplierRequest;
use App\Http\Requests\Api\UpdateShopPricingRequest;
use App\Models\Enterprise;
use App\Models\Product;
use App\Models\SupplierPriceList;
use App\Services\SupplierPriceListImportService;
use App\Support\SearchNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPriceListController extends Controller
{
    use ResolvesShopTeam;

    public function index(Request $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('viewAny', Product::class);

        $lists = SupplierPriceList::query()
            ->with(['enterprise:id,name', 'currency:id,code,symbol'])
            ->withCount('lines')
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SupplierPriceList $list): array => $this->formatList($list))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $lists,
        ]);
    }

    public function importSchema(Request $request, SupplierPriceListImportService $importer): JsonResponse
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
                'required_columns' => SupplierPriceListImportService::REQUIRED_COLUMNS,
                'optional_columns' => SupplierPriceListImportService::OPTIONAL_COLUMNS,
                'sample_csv' => $importer->templateContents(),
                'markup_percent' => $team->shopDefaultMarkupPercent(),
            ],
        ]);
    }

    public function import(
        ImportSupplierPriceListRequest $request,
        SupplierPriceListImportService $importer,
    ): JsonResponse {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $validated = $request->validated();
        $supplier = Enterprise::query()->where('type_id', 2)->findOrFail($validated['enterprise_id']);
        $markup = array_key_exists('markup_percent', $validated) && $validated['markup_percent'] !== null
            ? (float) $validated['markup_percent']
            : $team->shopDefaultMarkupPercent();

        try
        {
            $result = $importer->import($request->file('file')->getRealPath(), $team, $supplier, [
                'name' => $validated['name'] ?? null,
                'markup_percent' => $markup,
                'currency_id' => (int) $validated['currency_id'],
                'valid_from' => $validated['valid_from'] ?? null,
                'valid_until' => $validated['valid_until'] ?? null,
            ]);
        } catch (\RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

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
            'data' => [
                'list' => $this->formatList($result['list']),
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ],
        ], $imported > 0 ? 200 : 422);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('viewAny', Product::class);

        $suppliers = Enterprise::query()
            ->suppliers()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Enterprise $enterprise): array => [
                'id' => $enterprise->id,
                'name' => $enterprise->name,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $suppliers,
        ]);
    }

    public function storeSupplier(StoreShopSupplierRequest $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $supplier = Enterprise::query()->create([
            'team_id' => $team->id,
            'type_id' => 2,
            'status_id' => 2,
            'name' => $request->validated('name'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
            ],
        ], 201);
    }

    public function pricing(Request $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('viewAny', Product::class);

        return response()->json([
            'success' => true,
            'data' => [
                'markup_percent' => $team->shopDefaultMarkupPercent(),
            ],
        ]);
    }

    public function updatePricing(UpdateShopPricingRequest $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $percent = round((float) $request->validated('markup_percent'), 2);
        $team->setSetting('shop_default_markup_percent', $percent, [
            'type' => 'float',
            'group' => 'shop',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'markup_percent' => $team->shopDefaultMarkupPercent(),
            ],
        ]);
    }

    public function quoteProducts(Request $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $query = Product::query()->with('currency');
        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '')
        {
            SearchNormalizer::applyColumnsNavbarConditions($query, ['name', 'code', 'barcode', 'oem'], $search);
        }

        $items = $query->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => $product->currentSellingPrice(),
                'currency' => $product->currency ? [
                    'id' => $product->currency->id,
                    'code' => $product->currency->code,
                    'symbol' => $product->currency->symbol,
                ] : null,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatList(SupplierPriceList $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'markup_percent' => (float) $list->markup_percent,
            'valid_from' => $list->valid_from?->toDateString(),
            'valid_until' => $list->valid_until?->toDateString(),
            'imported_at' => $list->imported_at?->toIso8601String(),
            'lines_count' => (int) ($list->lines_count ?? $list->lines()->count()),
            'supplier' => $list->enterprise ? [
                'id' => $list->enterprise->id,
                'name' => $list->enterprise->name,
            ] : null,
            'currency' => $list->currency ? [
                'id' => $list->currency->id,
                'code' => $list->currency->code,
                'symbol' => $list->currency->symbol,
            ] : null,
        ];
    }
}
