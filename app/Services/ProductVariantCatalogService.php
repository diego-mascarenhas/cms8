<?php

namespace App\Services;

use App\Enums\ProductStockStatus;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class ProductVariantCatalogService
{
    /**
     * @param  list<array{name: string, values: list<string>}>  $options
     * @param  list<array<string, mixed>>|null  $variants
     */
    public function sync(Product $product, array $options, ?array $variants = null): void
    {
        $options = $this->normalizeOptions($options);

        if ($options === [])
        {
            $product->options()->delete();
            ProductVariant::withoutGlobalScope('team')
                ->where('product_id', $product->id)
                ->delete();
            $this->ensureDefaultVariant($product);
            $this->mirrorDefaultVariantToProduct($product);

            return;
        }

        $valueMap = $this->syncOptions($product, $options);
        $this->syncVariants($product, $options, $valueMap, $variants);
        $this->mirrorDefaultVariantToProduct($product);
    }

    public function ensureDefaultVariant(Product $product): ProductVariant
    {
        $variant = $product->variants()
            ->withoutGlobalScope('team')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $payload = $this->variantPayloadFromProduct($product, true, 0);

        if ($variant)
        {
            $variant->fill($payload)->save();

            return $variant;
        }

        return ProductVariant::withoutGlobalScope('team')->create(array_merge(
            $payload,
            [
                'team_id' => $product->team_id,
                'product_id' => $product->id,
            ],
        ));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array{name: string, values: list<string>}>
     */
    public function optionsFromValidated(array $validated): array
    {
        if (isset($validated['options']) && is_array($validated['options']) && $validated['options'] !== [])
        {
            return $this->normalizeOptions($validated['options']);
        }

        $options = [];
        $sizes = $this->stringList($validated['size_options'] ?? []);
        $colors = $this->stringList($validated['color_options'] ?? []);
        $flavors = $this->stringList($validated['flavor_options'] ?? []);

        if ($sizes !== [])
        {
            $options[] = ['name' => 'Talle', 'values' => $sizes];
        }
        if ($colors !== [])
        {
            $options[] = ['name' => 'Color', 'values' => $colors];
        }
        if ($flavors !== [])
        {
            $options[] = ['name' => 'Gusto', 'values' => $flavors];
        }

        return $options;
    }

    /**
     * @param  list<array{name?: mixed, values?: mixed}>  $options
     * @return list<array{name: string, values: list<string>}>
     */
    private function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach (array_slice($options, 0, 3) as $option)
        {
            $name = trim((string) ($option['name'] ?? ''));
            $values = $this->stringList($option['values'] ?? []);
            if ($name === '' || $values === [])
            {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'values' => $values,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{name: string, values: list<string>}>  $options
     * @return array<string, array<string, ProductOptionValue>>
     */
    private function syncOptions(Product $product, array $options): array
    {
        $keepOptionIds = [];
        $valueMap = [];

        foreach ($options as $position => $optionData)
        {
            $option = ProductOption::query()->firstOrNew([
                'product_id' => $product->id,
                'name' => $optionData['name'],
            ]);
            $option->fill([
                'team_id' => $product->team_id,
                'position' => $position,
            ])->save();
            $keepOptionIds[] = $option->id;
            $valueMap[$option->name] = [];

            $keepValueIds = [];
            foreach ($optionData['values'] as $valuePosition => $value)
            {
                $row = ProductOptionValue::query()->firstOrNew([
                    'product_option_id' => $option->id,
                    'value' => $value,
                ]);
                $row->fill([
                    'team_id' => $product->team_id,
                    'position' => $valuePosition,
                ])->save();
                $keepValueIds[] = $row->id;
                $valueMap[$option->name][$value] = $row;
            }

            ProductOptionValue::query()
                ->where('product_option_id', $option->id)
                ->whereNotIn('id', $keepValueIds)
                ->delete();
        }

        ProductOption::query()
            ->where('product_id', $product->id)
            ->whereNotIn('id', $keepOptionIds)
            ->delete();

        return $valueMap;
    }

    /**
     * @param  list<array{name: string, values: list<string>}>  $options
     * @param  array<string, array<string, ProductOptionValue>>  $valueMap
     * @param  list<array<string, mixed>>|null  $variants
     */
    private function syncVariants(Product $product, array $options, array $valueMap, ?array $variants): void
    {
        $combinations = $this->cartesian($options);
        $incoming = $this->indexIncomingVariants($variants ?? []);
        $keepIds = [];

        foreach ($combinations as $position => $combo)
        {
            $key = $this->comboKey($combo);
            $incomingRow = $incoming[$key] ?? [];
            $valueIds = [];
            foreach ($combo as $optionName => $value)
            {
                $valueIds[] = $valueMap[$optionName][$value]->id;
            }

            $variant = $this->findVariantByValues($product, $valueIds);
            $payload = array_merge(
                $this->variantPayloadFromProduct($product, $position === 0, $position),
                $this->variantOverrides($incomingRow),
            );

            if ($variant)
            {
                $variant->fill($payload)->save();
            } else
            {
                $variant = ProductVariant::withoutGlobalScope('team')->create(array_merge(
                    $payload,
                    [
                        'team_id' => $product->team_id,
                        'product_id' => $product->id,
                    ],
                ));
            }

            $variant->optionValues()->sync($valueIds);
            $keepIds[] = $variant->id;
        }

        ProductVariant::withoutGlobalScope('team')
            ->where('product_id', $product->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function variantPayloadFromProduct(Product $product, bool $isDefault, int $position): array
    {
        return [
            'sku' => null,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'stock_status' => $product->stock_status instanceof ProductStockStatus
                ? $product->stock_status
                : ProductStockStatus::InStock,
            'manage_stock' => (bool) $product->manage_stock,
            'stock_quantity' => $product->manage_stock ? $product->stock_quantity : null,
            'is_default' => $isDefault,
            'position' => $position,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function variantOverrides(array $row): array
    {
        $overrides = [];
        if (array_key_exists('sku', $row))
        {
            $sku = trim((string) $row['sku']);
            $overrides['sku'] = $sku !== '' ? $sku : null;
        }
        if (array_key_exists('price', $row) && $row['price'] !== null && $row['price'] !== '')
        {
            $overrides['price'] = $row['price'];
        }
        if (array_key_exists('sale_price', $row))
        {
            $overrides['sale_price'] = $row['sale_price'] !== '' ? $row['sale_price'] : null;
        }
        if (array_key_exists('stock_status', $row) && $row['stock_status'])
        {
            $overrides['stock_status'] = $row['stock_status'];
        }
        if (array_key_exists('manage_stock', $row))
        {
            $overrides['manage_stock'] = (bool) (int) $row['manage_stock'];
        }
        if (array_key_exists('stock_quantity', $row))
        {
            $overrides['stock_quantity'] = $overrides['manage_stock'] ?? false
                ? $row['stock_quantity']
                : null;
        }

        return $overrides;
    }

    /**
     * @param  list<int>  $valueIds
     */
    private function findVariantByValues(Product $product, array $valueIds): ?ProductVariant
    {
        $sorted = collect($valueIds)->sort()->values()->all();

        return $product->variants()
            ->withoutGlobalScope('team')
            ->with('optionValues')
            ->get()
            ->first(function (ProductVariant $variant) use ($sorted): bool
            {
                $current = $variant->optionValues->pluck('id')->sort()->values()->all();

                return $current === $sorted;
            });
    }

    /**
     * @param  list<array{name: string, values: list<string>}>  $options
     * @return list<array<string, string>>
     */
    private function cartesian(array $options): array
    {
        $combos = [[]];

        foreach ($options as $option)
        {
            $next = [];
            foreach ($combos as $combo)
            {
                foreach ($option['values'] as $value)
                {
                    $next[] = array_merge($combo, [$option['name'] => $value]);
                }
            }
            $combos = $next;
        }

        return $combos;
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     * @return array<string, array<string, mixed>>
     */
    private function indexIncomingVariants(array $variants): array
    {
        $indexed = [];
        foreach ($variants as $row)
        {
            $values = $row['option_values'] ?? [];
            if (! is_array($values))
            {
                continue;
            }
            $normalized = [];
            foreach ($values as $name => $value)
            {
                $normalized[trim((string) $name)] = trim((string) $value);
            }
            $indexed[$this->comboKey($normalized)] = $row;
        }

        return $indexed;
    }

    /**
     * @param  array<string, string>  $combo
     */
    private function comboKey(array $combo): string
    {
        ksort($combo);

        return collect($combo)
            ->map(fn (string $value, string $name): string => $name.'='.$value)
            ->implode('|');
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return Collection::wrap(is_array($value) ? $value : [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function mirrorDefaultVariantToProduct(Product $product): void
    {
        $default = $product->variants()
            ->withoutGlobalScope('team')
            ->where('is_default', true)
            ->orderBy('id')
            ->first()
            ?? $product->variants()->withoutGlobalScope('team')->orderBy('id')->first();

        if (! $default)
        {
            return;
        }

        $product->forceFill([
            'price' => $default->price,
            'sale_price' => $default->sale_price,
            'stock_status' => $default->stock_status,
            'manage_stock' => $default->manage_stock,
            'stock_quantity' => $default->stock_quantity,
        ])->saveQuietly();
    }
}
