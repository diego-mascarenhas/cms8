<?php

namespace App\Services;

use App\Enums\ProductCatalogStatus;
use App\Enums\ProductStockStatus;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCsvImportService
{
    public const REQUIRED_COLUMNS = ['code', 'name', 'price', 'category'];

    /**
     * Ready-to-import demo catalogue (real product photos) shipped with the app.
     */
    public const DEMO_CATALOG_PATH = 'database/samples/products-demo.csv';

    public const DEMO_CATALOG_LIMIT = 30;

    public const OPTIONAL_COLUMNS = [
        'description',
        'short_description',
        'sale_price',
        'currency',
        'store',
        'catalog_status',
        'stock_status',
        'manage_stock',
        'stock_quantity',
        'size_options',
        'color_options',
        'whatsapp_enabled',
        'image',
    ];

    /**
     * Header aliases so Spanish exports map onto the canonical column names.
     *
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'codigo' => 'code',
        'sku' => 'code',
        'nombre' => 'name',
        'descripcion' => 'description',
        'descripcion_corta' => 'short_description',
        'resumen' => 'short_description',
        'precio' => 'price',
        'precio_oferta' => 'sale_price',
        'oferta' => 'sale_price',
        'moneda' => 'currency',
        'sucursal' => 'store',
        'categoria' => 'category',
        'estado' => 'catalog_status',
        'stock' => 'stock_status',
        'gestionar_stock' => 'manage_stock',
        'cantidad' => 'stock_quantity',
        'cantidad_stock' => 'stock_quantity',
        'talles' => 'size_options',
        'tallas' => 'size_options',
        'colores' => 'color_options',
        'whatsapp' => 'whatsapp_enabled',
        'imagen' => 'image',
    ];

    /**
     * Main branch id per team, so a large file resolves it once instead of per row.
     *
     * @var array<int, int>
     */
    private array $mainStoreIds = [];

    /**
     * Import (create or update by `code`) the products described in a CSV file.
     *
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(string $absolutePath, int $teamId): array
    {
        $rows = $this->readRows($absolutePath);

        if ($rows === [])
        {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [__('The file has no data rows.')]];
        }

        $missingHeaders = array_diff(self::REQUIRED_COLUMNS, array_keys($rows[0]['values']));
        if ($missingHeaders !== [])
        {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => count($rows),
                'errors' => [__('Missing required columns: :columns', ['columns' => implode(', ', $missingHeaders)])],
            ];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row)
        {
            try
            {
                $wasCreated = DB::transaction(fn (): bool => $this->importRow($row['values'], $teamId));
                $wasCreated ? $created++ : $updated++;
            } catch (\Throwable $e)
            {
                $skipped++;
                if (count($errors) < 20)
                {
                    $errors[] = __('Row :line: :message', ['line' => $row['line'], 'message' => $e->getMessage()]);
                }
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Sample file offered from the import screen so users start from a valid layout.
     */
    public function templateContents(): string
    {
        $header = array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS);

        $rows = [
            [
                'REM-001', 'Remera algodón', '12500.00', 'Indumentaria',
                'Remera de algodón peinado 24/1.', 'Remera unisex', '9900.00', 'ARS', 'Principal',
                'publish', 'instock', '1', '25', 'S|M|L|XL', 'Negro|Blanco', '1', 'https://ejemplo.com/remera.jpg',
            ],
            [
                'TAZ-002', 'Taza cerámica', '6800.00', 'Bazar',
                'Taza de cerámica 350ml.', '', '', 'ARS', 'Principal',
                'publish', 'instock', '0', '', '', 'Blanco', '1', '',
            ],
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header);
        foreach ($rows as $row)
        {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /**
     * Demo catalogue contents plus how many products it holds.
     *
     * @return array{filename: string, csv: string, products: int}
     */
    public function demoCatalog(): array
    {
        $path = base_path(self::DEMO_CATALOG_PATH);

        if (! is_file($path))
        {
            return ['filename' => 'cms8-productos-ejemplo.csv', 'csv' => $this->templateContents(), 'products' => 2];
        }

        $rows = array_slice($this->readRows($path), 0, self::DEMO_CATALOG_LIMIT);

        return [
            'filename' => 'cms8-catalogo-demo.csv',
            'csv' => $this->csvFromRows($rows),
            'products' => count($rows),
        ];
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     */
    private function csvFromRows(array $rows): string
    {
        $header = array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS);
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header);
        foreach ($rows as $row)
        {
            $values = $row['values'];
            fputcsv($handle, array_map(fn (string $column): string => $values[$column] ?? '', $header));
        }
        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /**
     * @param  array<string, string>  $values
     * @return bool True when a new product was created, false when an existing one was updated.
     */
    private function importRow(array $values, int $teamId): bool
    {
        $code = trim($values['code'] ?? '');
        $name = trim($values['name'] ?? '');

        if ($code === '')
        {
            throw new \RuntimeException(__('The column :column is required.', ['column' => 'code']));
        }

        if ($name === '')
        {
            throw new \RuntimeException(__('The column :column is required.', ['column' => 'name']));
        }

        $price = $this->parseDecimal($values['price'] ?? null);
        if ($price === null || $price < 0)
        {
            throw new \RuntimeException(__('The column :column is required.', ['column' => 'price']));
        }

        $catalogStatus = $this->parseCatalogStatus($values['catalog_status'] ?? null);
        $manageStock = $this->parseBoolean($values['manage_stock'] ?? null, false);
        $stockQuantity = $this->parseInteger($values['stock_quantity'] ?? null);

        $product = Product::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('code', $code)
            ->first();

        $payload = [
            'team_id' => $teamId,
            'name' => $name,
            'code' => $code,
            'description' => trim($values['description'] ?? ''),
            'short_description' => $this->nullableString($values['short_description'] ?? null),
            'price' => round($price, 2),
            'sale_price' => $this->parseDecimal($values['sale_price'] ?? null),
            'currency_id' => $this->resolveCurrencyId($values['currency'] ?? null),
            'store_id' => $this->resolveStoreId($values['store'] ?? null, $teamId),
            'category_id' => $this->resolveCategoryId($values['category'] ?? null, $teamId),
            'catalog_status' => $catalogStatus,
            'stock_status' => $this->parseStockStatus($values['stock_status'] ?? null),
            'manage_stock' => $manageStock,
            'stock_quantity' => $manageStock ? ($stockQuantity ?? 0) : null,
            'size_options' => $this->parseList($values['size_options'] ?? null),
            'color_options' => $this->parseList($values['color_options'] ?? null),
            'whatsapp_enabled' => $this->parseBoolean($values['whatsapp_enabled'] ?? null, true),
            'image' => $this->nullableString($values['image'] ?? null),
        ];

        if ($product === null)
        {
            Product::withoutGlobalScope('team')->create($payload);

            return true;
        }

        $product->fill($payload)->save();

        return false;
    }

    /**
     * Normalized rows keyed by canonical column name, keeping the source line for error messages.
     *
     * @return list<array{line: int, values: array<string, string>}>
     */
    private function readRows(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false)
        {
            return [];
        }

        $delimiter = $this->detectDelimiter($absolutePath);
        $headers = null;
        $rows = [];
        $line = 0;

        while (($raw = fgetcsv($handle, 0, $delimiter)) !== false)
        {
            $line++;

            if ($raw === [null] || $raw === false)
            {
                continue;
            }

            if ($headers === null)
            {
                $headers = $this->normalizeHeaders($raw);

                continue;
            }

            if ($this->isBlankRow($raw))
            {
                continue;
            }

            $values = [];
            foreach ($headers as $index => $header)
            {
                if ($header === '')
                {
                    continue;
                }
                $values[$header] = isset($raw[$index]) ? trim((string) $raw[$index]) : '';
            }

            $rows[] = ['line' => $line, 'values' => $values];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string|null>  $raw
     * @return list<string>
     */
    private function normalizeHeaders(array $raw): array
    {
        $headers = [];
        foreach ($raw as $index => $value)
        {
            $header = (string) $value;
            if ($index === 0)
            {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
            }

            $header = Str::of($header)->trim()->lower()->ascii()->replace([' ', '-'], '_')->toString();
            $header = preg_replace('/[^a-z0-9_]/', '', $header) ?? $header;

            $headers[] = self::HEADER_ALIASES[$header] ?? $header;
        }

        return $headers;
    }

    private function detectDelimiter(string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false)
        {
            return ',';
        }

        $firstLine = (string) fgets($handle);
        fclose($handle);

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * @param  list<string|null>  $raw
     */
    private function isBlankRow(array $raw): bool
    {
        foreach ($raw as $value)
        {
            if (trim((string) $value) !== '')
            {
                return false;
            }
        }

        return true;
    }

    private function resolveCurrencyId(?string $value): int
    {
        $code = strtoupper(trim((string) $value));

        if ($code !== '')
        {
            $currencyId = Currency::query()->where('code', $code)->value('id');
            if ($currencyId)
            {
                return (int) $currencyId;
            }

            throw new \RuntimeException(__('Unknown currency :code.', ['code' => $code]));
        }

        $fallback = Currency::query()->where('status', true)->where('code', 'ARS')->value('id')
            ?? Currency::query()->where('status', true)->orderBy('code')->value('id');

        if (! $fallback)
        {
            throw new \RuntimeException(__('There are no active currencies configured.'));
        }

        return (int) $fallback;
    }

    /**
     * Resolve the branch by code or name, creating the main branch first so a file that
     * names it (the usual "Principal") also works on a team that never opened one.
     */
    private function resolveStoreId(?string $value, int $teamId): int
    {
        $mainStoreId = $this->mainStoreIds[$teamId] ??= (int) Store::ensureMainStoreForTeam($teamId)->id;

        $needle = trim((string) $value);
        if ($needle === '')
        {
            return $mainStoreId;
        }

        $storeId = Store::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where(function ($query) use ($needle)
            {
                $query->where('code', $needle)->orWhere('name', $needle);
            })
            ->value('id');

        if ($storeId)
        {
            return (int) $storeId;
        }

        throw new \RuntimeException(__('Unknown branch :name.', ['name' => $needle]));
    }

    /**
     * Category for the products module, created on the fly when the name is new for the team.
     */
    private function resolveCategoryId(?string $value, int $teamId): int
    {
        $name = trim((string) $value);
        if ($name === '')
        {
            throw new \RuntimeException(__('The column :column is required.', ['column' => 'category']));
        }

        $moduleId = (int) (Module::query()->where('key', 'products')->value('id') ?? 0);
        if ($moduleId === 0)
        {
            throw new \RuntimeException(__('The products module is not installed, categories cannot be resolved.'));
        }

        $category = Category::query()
            ->where('team_id', $teamId)
            ->where('module_id', $moduleId)
            ->where('name', $name)
            ->first();

        if ($category)
        {
            return (int) $category->id;
        }

        return (int) Category::query()->create([
            'name' => $name,
            'module_id' => $moduleId,
            'team_id' => $teamId,
            'description' => null,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ])->id;
    }

    private function parseCatalogStatus(?string $value): ProductCatalogStatus
    {
        $raw = Str::of((string) $value)->trim()->lower()->ascii()->toString();

        return match ($raw)
        {
            '', 'publish', 'published', 'publicado', 'activo', 'active', '1' => ProductCatalogStatus::Publish,
            'draft', 'borrador', '0' => ProductCatalogStatus::Draft,
            'pending', 'pendiente' => ProductCatalogStatus::Pending,
            'private', 'privado' => ProductCatalogStatus::Private,
            default => throw new \RuntimeException(__('Unknown value :value for :column.', ['value' => $value, 'column' => 'catalog_status'])),
        };
    }

    private function parseStockStatus(?string $value): ProductStockStatus
    {
        $raw = Str::of((string) $value)->trim()->lower()->ascii()->replace([' ', '-', '_'], '')->toString();

        return match ($raw)
        {
            '', 'instock', 'enstock', 'disponible', 'hay' => ProductStockStatus::InStock,
            'outofstock', 'sinstock', 'agotado' => ProductStockStatus::OutOfStock,
            'onbackorder', 'apedido', 'encargo' => ProductStockStatus::OnBackorder,
            default => throw new \RuntimeException(__('Unknown value :value for :column.', ['value' => $value, 'column' => 'stock_status'])),
        };
    }

    private function parseBoolean(?string $value, bool $default): bool
    {
        $raw = Str::of((string) $value)->trim()->lower()->ascii()->toString();

        if ($raw === '')
        {
            return $default;
        }

        return in_array($raw, ['1', 'true', 'si', 'yes', 'y', 'x', 'on'], true);
    }

    private function parseInteger(?string $value): ?int
    {
        $raw = preg_replace('/[^0-9\-]/', '', (string) $value) ?? '';

        return $raw === '' || $raw === '-' ? null : (int) $raw;
    }

    /**
     * Accepts both "1234.56" and "1.234,56"; the rightmost separator wins as decimal mark.
     */
    private function parseDecimal(?string $value): ?float
    {
        $raw = preg_replace('/[^0-9,.\-]/', '', (string) $value) ?? '';
        if ($raw === '' || $raw === '-')
        {
            return null;
        }

        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');

        if ($lastComma !== false && $lastDot !== false)
        {
            $decimal = $lastComma > $lastDot ? ',' : '.';
            $thousands = $decimal === ',' ? '.' : ',';
            $raw = str_replace($thousands, '', $raw);
            $raw = str_replace($decimal, '.', $raw);
        } elseif ($lastComma !== false)
        {
            $raw = substr_count($raw, ',') === 1 && strlen($raw) - $lastComma - 1 <= 2
                ? str_replace(',', '.', $raw)
                : str_replace(',', '', $raw);
        } elseif ($lastDot !== false && substr_count($raw, '.') > 1)
        {
            $raw = str_replace('.', '', $raw);
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    /**
     * @return list<string>
     */
    private function parseList(?string $value): array
    {
        $raw = trim((string) $value);
        if ($raw === '')
        {
            return [];
        }

        $parts = preg_split('/[|,;]/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $part): bool => $part !== ''));
    }

    private function nullableString(?string $value): ?string
    {
        $raw = trim((string) $value);

        return $raw === '' ? null : $raw;
    }
}
