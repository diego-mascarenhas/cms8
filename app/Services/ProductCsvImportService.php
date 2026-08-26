<?php

namespace App\Services;

use App\Enums\ProductCatalogStatus;
use App\Enums\ProductStockStatus;
use App\Models\Brand;
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

    public const DEFAULT_DEMO_CATALOG = 'mixto';

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
        'brand',
        'assortment_size',
        'size_options',
        'color_options',
        'flavor_options',
        'whatsapp_enabled',
        'image',
        'barcode',
        'oem',
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
        'marca' => 'brand',
        'combo' => 'assortment_size',
        'docena' => 'assortment_size',
        'estado' => 'catalog_status',
        'stock' => 'stock_status',
        'gestionar_stock' => 'manage_stock',
        'cantidad' => 'stock_quantity',
        'cantidad_stock' => 'stock_quantity',
        'talles' => 'size_options',
        'tallas' => 'size_options',
        'colores' => 'color_options',
        'gustos' => 'flavor_options',
        'toppings' => 'flavor_options',
        'whatsapp' => 'whatsapp_enabled',
        'imagen' => 'image',
        'ean' => 'barcode',
        'upc' => 'barcode',
        'codigo_barras' => 'barcode',
        'codigo_de_barras' => 'barcode',
        'nro_oem' => 'oem',
        'codigo_oem' => 'oem',
        'nro_original' => 'oem',
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
            $this->templateValues([
                'code' => 'PAS-010',
                'name' => 'Pastilla de freno',
                'price' => '18900.00',
                'category' => 'Autopartes',
                'description' => 'Pastilla delantera. El vehículo compatible va en fitment, no acá.',
                'short_description' => 'Pastilla Bosch',
                'currency' => 'ARS',
                'store' => 'Principal',
                'catalog_status' => 'publish',
                'stock_status' => 'instock',
                'manage_stock' => '1',
                'stock_quantity' => '12',
                'brand' => 'Bosch',
                'whatsapp_enabled' => '1',
                'barcode' => '7791234567890',
                'oem' => '7H0 698 151 D',
            ]),
            $this->templateValues([
                'code' => 'REM-001',
                'name' => 'Remera algodón',
                'price' => '12500.00',
                'category' => 'Indumentaria',
                'description' => 'Remera de algodón peinado 24/1.',
                'short_description' => 'Remera unisex',
                'sale_price' => '9900.00',
                'currency' => 'ARS',
                'store' => 'Principal',
                'catalog_status' => 'publish',
                'stock_status' => 'instock',
                'manage_stock' => '1',
                'stock_quantity' => '25',
                'brand' => 'Nike',
                'size_options' => 'S|M|L|XL',
                'color_options' => 'Negro|Blanco',
                'whatsapp_enabled' => '1',
                'image' => 'https://ejemplo.com/remera.jpg',
            ]),
            $this->templateValues([
                'code' => 'EMP-012',
                'name' => 'Docena de empanadas',
                'price' => '9800.00',
                'category' => 'Comida',
                'description' => 'Docena a elección. Cada gusto es una variante.',
                'short_description' => 'Docena mixta',
                'currency' => 'ARS',
                'store' => 'todas',
                'catalog_status' => 'publish',
                'stock_status' => 'instock',
                'manage_stock' => '0',
                'brand' => '',
                'assortment_size' => '12',
                'flavor_options' => 'Carne|Pollo|JyQ|Cebolla',
                'whatsapp_enabled' => '1',
            ]),
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
     * @param  array<string, string>  $values
     * @return list<string>
     */
    private function templateValues(array $values): array
    {
        return array_map(
            fn (string $column): string => $values[$column] ?? '',
            array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS),
        );
    }

    /**
     * Bundled industry catalogues. Keys are used in ?catalog= on the sample endpoint.
     *
     * @return array<string, array{file: string, filename: string, label: string, description: string}>
     */
    public static function demoCatalogDefinitions(): array
    {
        return [
            'mixto' => [
                'file' => self::DEMO_CATALOG_PATH,
                'filename' => 'cms8-catalogo-mixto.csv',
                'label' => __('Showroom mixto'),
                'description' => __('Ropa, calzado, deco, belleza y tecnología.'),
            ],
            'ropa' => [
                'file' => 'database/samples/catalogs/ropa.csv',
                'filename' => 'cms8-catalogo-ropa.csv',
                'label' => __('Ropa'),
                'description' => __('Indumentaria, calzado y accesorios con talles y colores.'),
            ],
            'autopartes' => [
                'file' => 'database/samples/catalogs/autopartes.csv',
                'filename' => 'cms8-catalogo-autopartes.csv',
                'label' => __('Autopartes'),
                'description' => __('Frenos, filtros, lubricantes y neumáticos con marca.'),
            ],
            'restaurante' => [
                'file' => 'database/samples/catalogs/restaurante.csv',
                'filename' => 'cms8-catalogo-restaurante.csv',
                'label' => __('Restaurante con delivery'),
                'description' => __('Pizzas, empanadas, combos y gustos. Catálogo para pedidos.'),
            ],
            'verduleria' => [
                'file' => 'database/samples/catalogs/verduleria.csv',
                'filename' => 'cms8-catalogo-verduleria.csv',
                'label' => __('Verdulería'),
                'description' => __('Frutas, verduras y almacén por kilo o bandeja.'),
            ],
            'ferreteria' => [
                'file' => 'database/samples/catalogs/ferreteria.csv',
                'filename' => 'cms8-catalogo-ferreteria.csv',
                'label' => __('Ferretería'),
                'description' => __('Herramientas, pinturería, electricidad y plomería.'),
            ],
            'belleza' => [
                'file' => 'database/samples/catalogs/belleza.csv',
                'filename' => 'cms8-catalogo-belleza.csv',
                'label' => __('Belleza y farmacia'),
                'description' => __('Maquillaje, cuidado de la piel y fragancias.'),
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, filename: string, products: int}>
     */
    public function demoCatalogs(): array
    {
        $catalogs = [];
        foreach (array_keys(self::demoCatalogDefinitions()) as $key)
        {
            $catalog = $this->demoCatalog($key);
            $meta = self::demoCatalogDefinitions()[$key];
            $catalogs[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'filename' => $catalog['filename'],
                'products' => $catalog['products'],
            ];
        }

        return $catalogs;
    }

    /**
     * Demo catalogue contents plus how many products it holds.
     *
     * @return array{key: string, filename: string, csv: string, products: int, label: string}
     */
    public function demoCatalog(?string $key = null): array
    {
        $definitions = self::demoCatalogDefinitions();
        $key = $key ?: self::DEFAULT_DEMO_CATALOG;
        if (! isset($definitions[$key]))
        {
            $key = self::DEFAULT_DEMO_CATALOG;
        }

        $meta = $definitions[$key];
        $path = base_path($meta['file']);

        if (! is_file($path))
        {
            return [
                'key' => $key,
                'filename' => $meta['filename'],
                'csv' => $this->templateContents(),
                'products' => 2,
                'label' => $meta['label'],
            ];
        }

        $rows = array_slice($this->readRows($path), 0, self::DEMO_CATALOG_LIMIT);

        return [
            'key' => $key,
            'filename' => $meta['filename'],
            'csv' => $this->csvFromRows($rows),
            'products' => count($rows),
            'label' => $meta['label'],
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

        $availability = $this->resolveStoreAvailability($values['store'] ?? null, $teamId);
        $barcode = Product::normalizeOptionalIdentifier($values['barcode'] ?? null);
        $this->assertUniqueBarcode($barcode, $teamId, $product?->id);

        $payload = [
            'team_id' => $teamId,
            'name' => $name,
            'code' => $code,
            'barcode' => $barcode,
            'oem' => Product::normalizeOptionalIdentifier($values['oem'] ?? null),
            'description' => trim($values['description'] ?? ''),
            'short_description' => $this->nullableString($values['short_description'] ?? null),
            'price' => round($price, 2),
            'sale_price' => $this->parseDecimal($values['sale_price'] ?? null),
            'currency_id' => $this->resolveCurrencyId($values['currency'] ?? null),
            'store_id' => $availability['store_id'],
            'available_in_all_stores' => $availability['all'],
            'category_id' => $this->resolveCategoryId($values['category'] ?? null, $teamId),
            'brand_id' => $this->resolveBrandId($values['brand'] ?? null, $teamId),
            'catalog_status' => $catalogStatus,
            'stock_status' => $this->parseStockStatus($values['stock_status'] ?? null),
            'manage_stock' => $manageStock,
            'stock_quantity' => $manageStock ? ($stockQuantity ?? 0) : null,
            'assortment_size' => $this->parseInteger($values['assortment_size'] ?? null),
            'whatsapp_enabled' => $this->parseBoolean($values['whatsapp_enabled'] ?? null, true),
            'image' => $this->nullableString($values['image'] ?? null),
        ];

        if ($product === null)
        {
            $product = Product::withoutGlobalScope('team')->create($payload);
            $product->syncStoreAvailability($availability['all'], $availability['store_ids']);
            $this->syncImportedVariants($product, $values);

            return true;
        }

        $product->fill($payload)->save();
        $product->syncStoreAvailability($availability['all'], $availability['store_ids']);
        $this->syncImportedVariants($product, $values);

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

    private function assertUniqueBarcode(?string $barcode, int $teamId, ?int $ignoreProductId): void
    {
        if ($barcode === null)
        {
            return;
        }

        $exists = Product::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('barcode', $barcode)
            ->when($ignoreProductId !== null, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->exists();

        if ($exists)
        {
            throw new \RuntimeException(__('The barcode has already been used in this team.'));
        }
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
     * Empty / "todas" = every branch. Several names separated by | restrict the product.
     *
     * @return array{all: bool, store_id: int, store_ids: list<int>}
     */
    private function resolveStoreAvailability(?string $value, int $teamId): array
    {
        $mainStoreId = $this->mainStoreIds[$teamId] ??= (int) Store::ensureMainStoreForTeam($teamId)->id;
        $needle = trim((string) $value);
        $normalized = mb_strtolower($needle);

        if ($needle === '' || in_array($normalized, ['todas', 'all', '*', 'todas las sucursales'], true))
        {
            return [
                'all' => true,
                'store_id' => $mainStoreId,
                'store_ids' => [],
            ];
        }

        $ids = [];
        foreach ($this->parseList($needle) as $name)
        {
            $ids[] = $this->resolveStoreId($name, $teamId);
        }

        $ids = array_values(array_unique($ids));

        return [
            'all' => false,
            'store_id' => $ids[0] ?? $mainStoreId,
            'store_ids' => $ids,
        ];
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

    private function resolveBrandId(?string $value, int $teamId): ?int
    {
        $name = trim((string) $value);
        if ($name === '')
        {
            return null;
        }

        $existing = Brand::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->get()
            ->first(fn (Brand $brand): bool => mb_strtolower(trim($brand->name)) === mb_strtolower($name));

        if ($existing)
        {
            return (int) $existing->id;
        }

        return (int) Brand::query()->create([
            'team_id' => $teamId,
            'name' => $name,
            'slug' => Str::slug($name) ?: null,
            'status' => true,
        ])->id;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function syncImportedVariants(Product $product, array $values): void
    {
        $catalog = app(ProductVariantCatalogService::class);
        $catalog->sync($product, $catalog->optionsFromValidated([
            'size_options' => $this->parseList($values['size_options'] ?? null),
            'color_options' => $this->parseList($values['color_options'] ?? null),
            'flavor_options' => $this->parseList($values['flavor_options'] ?? null),
        ]));
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
