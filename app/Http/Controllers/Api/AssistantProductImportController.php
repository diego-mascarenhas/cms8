<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyAllTeamProductsRequest;
use App\Http\Requests\ImportProductCsvRequest;
use App\Models\Product;
use App\Models\ShoppingCartItem;
use App\Services\ProductCsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantProductImportController extends Controller
{
    /**
     * Column contract and a sample file so the Assistant can show the expected layout.
     */
    public function show(Request $request, ProductCsvImportService $importer): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $this->authorize('create', Product::class);

        return response()->json([
            'success' => true,
            'data' => [
                'required_columns' => ProductCsvImportService::REQUIRED_COLUMNS,
                'optional_columns' => ProductCsvImportService::OPTIONAL_COLUMNS,
                'sample_csv' => $importer->templateContents(),
                'demo_products' => $importer->demoCatalog()['products'],
                'demo_catalogs' => $importer->demoCatalogs(),
                'products_count' => Product::withoutGlobalScope('team')->where('team_id', $team->id)->count(),
            ],
        ]);
    }

    /**
     * Full demo catalogue with real product photo URLs, fetched on demand.
     */
    public function sample(Request $request, ProductCsvImportService $importer): JsonResponse
    {
        if (! $request->user()?->currentTeam)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $this->authorize('create', Product::class);

        $key = $request->query('catalog');
        $catalog = is_string($key) ? $key : null;

        return response()->json([
            'success' => true,
            'data' => $importer->demoCatalog($catalog),
        ]);
    }

    /**
     * Create or update the team catalogue from an uploaded CSV, matching by product code.
     */
    public function store(ImportProductCsvRequest $request, ProductCsvImportService $importer): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
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
                'products_count' => Product::withoutGlobalScope('team')->where('team_id', $team->id)->count(),
            ]),
        ], $imported > 0 ? 200 : 422);
    }

    /**
     * Delete every product of the current team after password confirmation.
     */
    public function destroy(DestroyAllTeamProductsRequest $request): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $query = Product::withoutGlobalScope('team')->where('team_id', $team->id);
        $deleted = (clone $query)->count();

        $productIds = (clone $query)->pluck('id');
        if ($productIds->isNotEmpty())
        {
            ShoppingCartItem::withoutGlobalScope('team')
                ->where('team_id', $team->id)
                ->whereIn('product_id', $productIds)
                ->delete();
        }

        $query->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted > 0
                ? __(':count products deleted.', ['count' => $deleted])
                : __('No había productos para eliminar.'),
            'data' => [
                'deleted' => $deleted,
                'products_count' => 0,
            ],
        ]);
    }
}
