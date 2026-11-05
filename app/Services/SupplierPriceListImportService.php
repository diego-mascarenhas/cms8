<?php

namespace App\Services;

use App\Enums\ProductCatalogStatus;
use App\Enums\ProductStockStatus;
use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Product;
use App\Models\SupplierPriceLine;
use App\Models\SupplierPriceList;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierPriceListImportService
{
    public const DRAFT_CATEGORY = 'Listas de proveedor';

    public const REQUIRED_COLUMNS = ['name', 'cost'];

    public const OPTIONAL_COLUMNS = ['code', 'barcode', 'oem', 'min_qty', 'unit'];

    /**
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'codigo' => 'code',
        'sku' => 'code',
        'supplier_code' => 'code',
        'nombre' => 'name',
        'costo' => 'cost',
        'coste' => 'cost',
        'precio' => 'cost',
        'price' => 'cost',
        'precio_costo' => 'cost',
        'codigo_barras' => 'barcode',
        'ean' => 'barcode',
        'cantidad_minima' => 'min_qty',
        'minimo' => 'min_qty',
        'unidad' => 'unit',
    ];

    public function templateContents(): string
    {
        return implode("\n", [
            'code,name,cost,barcode,oem',
            'REM-001,Remera blanca,1000,,',
            'EMP-012,Empanada de carne,500,,',
        ])."\n";
    }

    /**
     * @param  array{name?: string|null, markup_percent: float, currency_id: int, valid_from?: string|null, valid_until?: string|null}  $meta
     * @return array{list: SupplierPriceList, created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(string $path, Team $team, Enterprise $supplier, array $meta): array
    {
        return $this->importRows($this->readRows($path), $team, $supplier, $meta);
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array{name?: string|null, markup_percent: float, currency_id: int, valid_from?: string|null, valid_until?: string|null}  $meta
     * @return array{list: SupplierPriceList, created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function importRows(array $rows, Team $team, Enterprise $supplier, array $meta): array
    {
        $currencyId = (int) $meta['currency_id'];
        $listMarkup = (float) $meta['markup_percent'];

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $accepted = [];

        foreach ($rows as $index => $row)
        {
            $lineNumber = $index + 2;
            try
            {
                $accepted[] = $this->prepareRow($row, $lineNumber);
            } catch (\RuntimeException $e)
            {
                $skipped++;
                $errors[] = $e->getMessage();
            }
        }

        $list = DB::transaction(function () use ($team, $supplier, $meta, $currencyId, $listMarkup, $accepted, &$created, &$updated, &$skipped, &$errors)
        {
            $list = SupplierPriceList::query()->create([
                'team_id' => $team->id,
                'enterprise_id' => $supplier->id,
                'name' => $this->listName($meta['name'] ?? null, $supplier),
                'currency_id' => $currencyId,
                'markup_percent' => $listMarkup,
                'valid_from' => $meta['valid_from'] ?? null,
                'valid_until' => $meta['valid_until'] ?? null,
                'imported_at' => now(),
            ]);

            foreach ($accepted as $row)
            {
                try
                {
                    $product = $this->matchProduct((int) $team->id, $row);
                    if ($product)
                    {
                        $this->applyCost($product, $row, $currencyId, $listMarkup);
                        $updated++;
                    } else
                    {
                        $product = $this->createDraftProduct((int) $team->id, $row, $currencyId, $listMarkup);
                        $created++;
                    }

                    SupplierPriceLine::query()->create([
                        'team_id' => $team->id,
                        'supplier_price_list_id' => $list->id,
                        'product_id' => $product->id,
                        'supplier_code' => $row['code'] !== '' ? $row['code'] : null,
                        'name' => $row['name'],
                        'unit_cost' => $row['cost'],
                        'min_qty' => $row['min_qty'],
                        'unit' => $row['unit'],
                    ]);
                } catch (\RuntimeException $e)
                {
                    $skipped++;
                    $errors[] = $e->getMessage();
                }
            }

            return $list;
        });

        $list->load(['enterprise', 'currency']);
        $list->setAttribute('lines_count', $created + $updated);

        return [
            'list' => $list,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array{code: string, name: string, cost: float, barcode: ?string, oem: ?string, min_qty: ?float, unit: ?string, line: int}
     */
    private function prepareRow(array $row, int $lineNumber): array
    {
        $name = trim($row['name'] ?? '');
        $costRaw = trim($row['cost'] ?? '');
        if ($name === '')
        {
            throw new \RuntimeException(__('Row :row is missing a name.', ['row' => $lineNumber]));
        }
        if ($costRaw === '' || ! is_numeric(str_replace(',', '.', $costRaw)))
        {
            throw new \RuntimeException(__('Row :row is missing a cost.', ['row' => $lineNumber]));
        }

        $cost = round((float) str_replace(',', '.', $costRaw), 2);
        if ($cost < 0)
        {
            throw new \RuntimeException(__('Row :row has a negative cost.', ['row' => $lineNumber]));
        }

        $code = strtoupper(trim($row['code'] ?? ''));
        $barcode = Product::normalizeOptionalIdentifier($row['barcode'] ?? null);
        $oem = Product::normalizeOptionalIdentifier($row['oem'] ?? null);
        $minRaw = trim($row['min_qty'] ?? '');
        $minQty = $minRaw !== '' && is_numeric(str_replace(',', '.', $minRaw))
            ? round((float) str_replace(',', '.', $minRaw), 2)
            : null;
        $unit = trim($row['unit'] ?? '');

        return [
            'code' => $code,
            'name' => $name,
            'cost' => $cost,
            'barcode' => $barcode,
            'oem' => $oem,
            'min_qty' => $minQty,
            'unit' => $unit !== '' ? Str::limit($unit, 32, '') : null,
            'line' => $lineNumber,
        ];
    }

    /**
     * @param  array{code: string, name: string, barcode: ?string, oem: ?string}  $row
     */
    private function matchProduct(int $teamId, array $row): ?Product
    {
        $base = Product::query()->where('team_id', $teamId);

        $compactCode = preg_replace('/\s+/', '', $row['code']) ?? '';
        if ($compactCode !== '')
        {
            $byCode = (clone $base)->where(function ($query) use ($row, $compactCode)
            {
                $query->where('code', $row['code'])
                    ->orWhereRaw("REPLACE(UPPER(code), ' ', '') = ?", [$compactCode]);
            })->first();
            if ($byCode)
            {
                return $byCode;
            }
        }

        if ($row['barcode'])
        {
            $byBarcode = (clone $base)->where('barcode', $row['barcode'])->first();
            if ($byBarcode)
            {
                return $byBarcode;
            }
        }

        if ($row['oem'])
        {
            $byOem = (clone $base)->where('oem', $row['oem'])->first();
            if ($byOem)
            {
                return $byOem;
            }
        }

        return (clone $base)->whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'])])->first();
    }

    /**
     * @param  array{cost: float, line: int}  $row
     */
    private function applyCost(Product $product, array $row, int $currencyId, float $listMarkup): void
    {
        if ((int) $product->currency_id !== $currencyId)
        {
            throw new \RuntimeException(__('Row :row uses a different currency than the product.', ['row' => $row['line']]));
        }

        $markup = $product->markup_percent !== null
            ? (float) $product->markup_percent
            : $listMarkup;

        $product->cost = $row['cost'];
        $product->markup_percent = $markup;
        $product->save();
    }

    /**
     * @param  array{code: string, name: string, cost: float, barcode: ?string, oem: ?string}  $row
     */
    private function createDraftProduct(int $teamId, array $row, int $currencyId, float $listMarkup): Product
    {
        $code = $row['code'] !== '' ? $row['code'] : $this->uniqueCode($teamId);
        if (Product::query()->where('team_id', $teamId)->where('code', $code)->exists())
        {
            throw new \RuntimeException(__('Row :row code is already used.', ['row' => $row['line']]));
        }
        if ($row['barcode'] && Product::query()->where('team_id', $teamId)->where('barcode', $row['barcode'])->exists())
        {
            throw new \RuntimeException(__('Row :row barcode is already used.', ['row' => $row['line']]));
        }

        return Product::query()->create([
            'team_id' => $teamId,
            'name' => $row['name'],
            'code' => $code,
            'barcode' => $row['barcode'],
            'oem' => $row['oem'],
            'description' => '',
            'cost' => $row['cost'],
            'markup_percent' => $listMarkup,
            'price' => Product::sellingPriceFromCost($row['cost'], $listMarkup),
            'currency_id' => $currencyId,
            'category_id' => $this->draftCategoryId($teamId),
            'available_in_all_stores' => true,
            'catalog_status' => ProductCatalogStatus::Draft,
            'stock_status' => ProductStockStatus::InStock,
            'manage_stock' => false,
            'whatsapp_enabled' => true,
            'status' => false,
        ]);
    }

    private function draftCategoryId(int $teamId): int
    {
        $moduleId = (int) (Module::query()->where('key', 'products')->value('id') ?? 0);
        if ($moduleId === 0)
        {
            throw new \RuntimeException(__('The products module is not installed, categories cannot be resolved.'));
        }

        $existing = Category::query()
            ->where('team_id', $teamId)
            ->where('module_id', $moduleId)
            ->where('name', self::DRAFT_CATEGORY)
            ->first();

        if ($existing)
        {
            return (int) $existing->id;
        }

        return (int) Category::query()->create([
            'name' => self::DRAFT_CATEGORY,
            'module_id' => $moduleId,
            'team_id' => $teamId,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ])->id;
    }

    private function uniqueCode(int $teamId): string
    {
        do
        {
            $code = 'LST-'.strtoupper(Str::random(8));
        } while (Product::query()->where('team_id', $teamId)->where('code', $code)->exists());

        return $code;
    }

    private function listName(?string $name, Enterprise $supplier): string
    {
        $trimmed = trim((string) $name);
        if ($trimmed !== '')
        {
            return Str::limit($trimmed, 255, '');
        }

        return Str::limit($supplier->name.' '.now()->toDateString(), 255, '');
    }

    /**
     * @return array{kind: string, supplier_name: null, currency: null, lines: list<array{code: string, name: string, cost: float, quantity: ?float, unit: ?string, barcode: ?string, oem: ?string}>}
     */
    public function previewCsv(string $path): array
    {
        $lines = [];
        foreach ($this->readRows($path) as $row)
        {
            $prepared = $this->prepareRow($row, count($lines) + 2);
            $lines[] = [
                'code' => $prepared['code'],
                'name' => $prepared['name'],
                'cost' => $prepared['cost'],
                'quantity' => $prepared['min_qty'],
                'unit' => $prepared['unit'],
                'barcode' => $prepared['barcode'],
                'oem' => $prepared['oem'],
            ];
        }

        return [
            'kind' => 'price_list',
            'supplier_name' => null,
            'currency' => null,
            'lines' => $lines,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function readRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false)
        {
            throw new \RuntimeException(__('The CSV file could not be read.'));
        }

        $first = fgets($handle);
        if ($first === false)
        {
            fclose($handle);

            throw new \RuntimeException(__('The CSV file is empty.'));
        }

        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($handle);
        $headerRow = fgetcsv($handle, 0, $delimiter);
        if (! is_array($headerRow))
        {
            fclose($handle);

            throw new \RuntimeException(__('The CSV file has no header.'));
        }

        $headers = [];
        foreach ($headerRow as $header)
        {
            $key = Str::of((string) $header)
                ->ascii()
                ->lower()
                ->replace(' ', '_')
                ->trim()
                ->toString();
            $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
            $headers[] = self::HEADER_ALIASES[$key] ?? $key;
        }

        foreach (self::REQUIRED_COLUMNS as $column)
        {
            if (! in_array($column, $headers, true))
            {
                fclose($handle);

                throw new \RuntimeException(__('The column :column is required.', ['column' => $column]));
            }
        }

        $rows = [];
        while (($raw = fgetcsv($handle, 0, $delimiter)) !== false)
        {
            if ($raw === [null] || (count($raw) === 1 && trim((string) $raw[0]) === ''))
            {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header)
            {
                $row[$header] = trim((string) ($raw[$index] ?? ''));
            }
            $rows[] = $row;
            if (count($rows) > 5000)
            {
                fclose($handle);

                throw new \RuntimeException(__('The CSV file may not contain more than 5000 rows.'));
            }
        }

        fclose($handle);

        return $rows;
    }
}
