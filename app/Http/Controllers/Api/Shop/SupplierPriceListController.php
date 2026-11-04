<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfirmSupplierPriceListRequest;
use App\Http\Requests\Api\ImportSupplierPriceListRequest;
use App\Http\Requests\Api\InterpretQuoteDocumentRequest;
use App\Http\Requests\Api\StoreShopSupplierRequest;
use App\Http\Requests\Api\UpdateShopPricingRequest;
use App\Models\Enterprise;
use App\Models\Product;
use App\Models\SupplierPriceList;
use App\Models\Team;
use App\Services\CommercialDocumentInterpreter;
use App\Services\QuoteDocumentService;
use App\Services\SupplierPriceListImportService;
use App\Support\SearchNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    public function preview(
        InterpretQuoteDocumentRequest $request,
        SupplierPriceListImportService $importer,
        CommercialDocumentInterpreter $documents,
    ): JsonResponse {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('create', Product::class);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        try
        {
            $document = in_array($extension, ['csv', 'txt'], true)
                ? $importer->previewCsv($file->getRealPath())
                : $documents->interpret($file->getRealPath(), $file->getClientOriginalName(), (int) $team->id);
        } catch (\RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $document,
        ]);
    }

    public function confirm(
        ConfirmSupplierPriceListRequest $request,
        SupplierPriceListImportService $importer,
    ): JsonResponse {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $validated = $request->validated();
        $supplier = ! empty($validated['enterprise_id'])
            ? Enterprise::query()->where('type_id', 2)->findOrFail($validated['enterprise_id'])
            : $this->supplierFromName($team, $validated['supplier_name'] ?? null);
        if ($supplier === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('Choose or create the supplier.'),
            ], 422);
        }

        $markup = array_key_exists('markup_percent', $validated) && $validated['markup_percent'] !== null
            ? (float) $validated['markup_percent']
            : $team->shopDefaultMarkupPercent();
        $rows = [];
        foreach ($validated['lines'] as $line)
        {
            $rows[] = [
                'code' => (string) ($line['code'] ?? ''),
                'name' => (string) $line['name'],
                'cost' => (string) $line['cost'],
                'barcode' => (string) ($line['barcode'] ?? ''),
                'oem' => (string) ($line['oem'] ?? ''),
                'min_qty' => isset($line['quantity']) ? (string) $line['quantity'] : '',
                'unit' => (string) ($line['unit'] ?? ''),
            ];
        }

        try
        {
            $result = $importer->importRows($rows, $team, $supplier, [
                'name' => $validated['name'] ?? null,
                'markup_percent' => $markup,
                'currency_id' => (int) $validated['currency_id'],
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

    public function import(
        ImportSupplierPriceListRequest $request,
        SupplierPriceListImportService $importer,
        CommercialDocumentInterpreter $documents,
    ): JsonResponse {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $validated = $request->validated();
        $supplier = ! empty($validated['enterprise_id'])
            ? Enterprise::query()->where('type_id', 2)->findOrFail($validated['enterprise_id'])
            : null;
        $markup = array_key_exists('markup_percent', $validated) && $validated['markup_percent'] !== null
            ? (float) $validated['markup_percent']
            : $team->shopDefaultMarkupPercent();
        $meta = [
            'name' => $validated['name'] ?? null,
            'markup_percent' => $markup,
            'currency_id' => (int) $validated['currency_id'],
            'valid_from' => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
        ];
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        try
        {
            if (in_array($extension, ['csv', 'txt'], true))
            {
                if ($supplier === null)
                {
                    return response()->json([
                        'success' => false,
                        'message' => __('Choose or create the supplier.'),
                    ], 422);
                }

                $result = $importer->import($file->getRealPath(), $team, $supplier, $meta);
            } else
            {
                $document = $documents->interpret($file->getRealPath(), $file->getClientOriginalName(), (int) $team->id);
                $supplier ??= $this->supplierFromName($team, $document['supplier_name']);
                if ($supplier === null)
                {
                    return response()->json([
                        'success' => false,
                        'message' => __('Choose or create the supplier.'),
                    ], 422);
                }

                $rows = [];
                foreach ($document['lines'] as $line)
                {
                    if ($line['cost'] === null)
                    {
                        continue;
                    }

                    $rows[] = [
                        'code' => $line['code'],
                        'name' => $line['name'],
                        'cost' => (string) $line['cost'],
                        'barcode' => (string) ($line['barcode'] ?? ''),
                        'oem' => (string) ($line['oem'] ?? ''),
                        'min_qty' => $line['quantity'] !== null ? (string) $line['quantity'] : '',
                        'unit' => (string) ($line['unit'] ?? ''),
                    ];
                }

                if ($rows === [])
                {
                    return response()->json([
                        'success' => false,
                        'message' => __('This file is an order without costs. Use it in the quote.'),
                    ], 422);
                }

                $result = $importer->importRows($rows, $team, $supplier, $meta);
            }
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

    public function quoteDocument(
        InterpretQuoteDocumentRequest $request,
        QuoteDocumentService $quotes,
    ): JsonResponse {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $file = $request->file('file');

        try
        {
            $document = $quotes->quoteLines($file->getRealPath(), $file->getClientOriginalName(), $team);
        } catch (\RuntimeException $e)
        {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $document,
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

    private function supplierFromName(Team $team, ?string $name): ?Enterprise
    {
        $name = trim((string) $name);
        if ($name === '')
        {
            return null;
        }

        $existing = Enterprise::query()
            ->suppliers()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing)
        {
            return $existing;
        }

        return Enterprise::query()->create([
            'team_id' => $team->id,
            'type_id' => 2,
            'status_id' => 2,
            'name' => Str::limit($name, 75, ''),
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
