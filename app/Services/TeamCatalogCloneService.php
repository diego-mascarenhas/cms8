<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use InvalidArgumentException;

class TeamCatalogCloneService
{
    /**
     * Clone products-module stores, categories (tree), and products from source team to target team.
     * Target can then edit data independently (e.g. demo team seeded from a client snapshot).
     *
     * @return array{stores: int, categories: int, products: int}
     *
     * @throws InvalidArgumentException
     */
    public function cloneCatalog(int $sourceTeamId, int $targetTeamId, bool $dryRun = false): array
    {
        if ($sourceTeamId === $targetTeamId)
        {
            throw new InvalidArgumentException('Source and target team must differ.');
        }

        $sourceTeam = Team::query()->find($sourceTeamId);
        $targetTeam = Team::query()->find($targetTeamId);
        if ($sourceTeam === null || $targetTeam === null)
        {
            throw new InvalidArgumentException('Source or target team not found.');
        }

        $module = Module::query()->where('key', 'products')->first();
        if ($module === null)
        {
            throw new InvalidArgumentException('Products module is not installed.');
        }

        $sourceStores = Store::withoutGlobalScope('team')
            ->where('team_id', $sourceTeamId)
            ->orderBy('id')
            ->get();

        $sourceCategories = Category::query()
            ->with('tags')
            ->where('team_id', $sourceTeamId)
            ->where('module_id', $module->id)
            ->get();

        $sourceProducts = Product::withoutGlobalScope('team')
            ->with(['brand', 'options.values', 'variants.optionValues.option'])
            ->where('team_id', $sourceTeamId)
            ->orderBy('id')
            ->get();

        if ($dryRun)
        {
            return [
                'stores' => $sourceStores->count(),
                'categories' => $sourceCategories->count(),
                'products' => $sourceProducts->count(),
            ];
        }

        $stats = ['stores' => 0, 'categories' => 0, 'products' => 0];

        Team::query()->getModel()->getConnection()->transaction(function () use (
            $targetTeam,
            $sourceStores,
            $sourceCategories,
            $sourceProducts,
            $module,
            $targetTeamId,
            &$stats
        ): void {
            $targetTeam->enableModule('products');

            /** @var array<int, int> */
            $storeMap = [];
            foreach ($sourceStores as $store)
            {
                $code = $this->uniqueStoreCode((string) $store->code, $targetTeamId);
                $new = Store::withoutGlobalScope('team')->create([
                    'team_id' => $targetTeamId,
                    'name' => $store->name,
                    'code' => $code,
                    'address' => $store->address,
                    'data' => $store->data,
                    'status' => $store->status,
                    'is_main' => $store->is_main,
                ]);
                $storeMap[$store->id] = $new->id;
                $stats['stores']++;
            }

            $this->normalizeSingleMainStoreFlag($targetTeamId);

            /** @var array<int, int> */
            $categoryMap = [];
            $remaining = $sourceCategories->keyBy('id');
            $guard = 0;
            while ($remaining->isNotEmpty() && $guard < 10000)
            {
                $guard++;
                $progress = false;
                foreach ($remaining as $id => $cat)
                {
                    $parentId = $cat->parent_id;
                    if ($parentId !== null && ! isset($categoryMap[$parentId]))
                    {
                        if (! $sourceCategories->contains('id', $parentId))
                        {
                            $parentId = null;
                        } else
                        {
                            continue;
                        }
                    }
                    $newParentId = $parentId !== null ? ($categoryMap[$parentId] ?? null) : null;

                    $new = Category::query()->create([
                        'name' => $cat->name,
                        'module_id' => $module->id,
                        'team_id' => $targetTeamId,
                        'description' => $cat->description,
                        'data' => $cat->data,
                        'parent_id' => $newParentId,
                        'order' => $cat->order,
                        'status' => $cat->status,
                    ]);

                    if ($cat->tags->isNotEmpty())
                    {
                        $new->syncTags($cat->tags->pluck('name')->all());
                    }

                    $categoryMap[$cat->id] = $new->id;
                    $remaining->forget($id);
                    $stats['categories']++;
                    $progress = true;
                }
                if (! $progress)
                {
                    throw new InvalidArgumentException('Could not resolve category parent chain (circular or missing parent).');
                }
            }

            foreach ($sourceProducts as $product)
            {
                $categoryId = $categoryMap[$product->category_id] ?? null;
                if ($categoryId === null)
                {
                    throw new InvalidArgumentException("Product {$product->id} references category {$product->category_id} that was not cloned.");
                }
                $storeId = $product->store_id !== null
                    ? ($storeMap[$product->store_id] ?? null)
                    : null;

                $code = $this->uniqueProductCode($product->code, $targetTeamId, $product->id);

                $clone = Product::withoutGlobalScope('team')->create([
                    'team_id' => $targetTeamId,
                    'name' => $product->name,
                    'code' => $code,
                    'description' => $product->description,
                    'short_description' => $product->short_description,
                    'price' => $product->price,
                    'sale_price' => $product->sale_price,
                    'currency_id' => $product->currency_id,
                    'category_id' => $categoryId,
                    'store_id' => $storeId,
                    'status' => $product->status,
                    'catalog_status' => $product->catalog_status,
                    'stock_status' => $product->stock_status,
                    'manage_stock' => $product->manage_stock,
                    'stock_quantity' => $product->stock_quantity,
                    'brand_id' => $this->clonedBrandId($product, $targetTeamId),
                    'assortment_size' => $product->assortment_size,
                    'whatsapp_enabled' => $product->whatsapp_enabled,
                    'image' => $product->image,
                ]);
                $product->loadMissing(['options.values', 'variants.optionValues.option']);
                app(ProductVariantCatalogService::class)->sync(
                    $clone,
                    $product->options->map(fn ($option): array => [
                        'name' => $option->name,
                        'values' => $option->values->pluck('value')->all(),
                    ])->values()->all(),
                    $product->variants->map(fn ($variant): array => [
                        'sku' => $variant->sku,
                        'price' => $variant->price,
                        'sale_price' => $variant->sale_price,
                        'stock_status' => $variant->stock_status?->value,
                        'manage_stock' => $variant->manage_stock,
                        'stock_quantity' => $variant->stock_quantity,
                        'option_values' => $variant->optionValues
                            ->mapWithKeys(fn ($value): array => [(string) $value->option?->name => $value->value])
                            ->all(),
                    ])->values()->all(),
                );
                $stats['products']++;
            }
        });

        return $stats;
    }

    private function clonedBrandId(Product $product, int $targetTeamId): ?int
    {
        $name = trim((string) $product->brand?->name);
        if ($name === '')
        {
            return null;
        }

        $existing = Brand::withoutGlobalScope('team')
            ->where('team_id', $targetTeamId)
            ->where('name', $name)
            ->first();

        if ($existing)
        {
            return (int) $existing->id;
        }

        return (int) Brand::query()->create([
            'team_id' => $targetTeamId,
            'name' => $name,
            'slug' => $product->brand?->slug,
            'status' => true,
        ])->id;
    }

    private function uniqueStoreCode(string $baseCode, int $targetTeamId): string
    {
        $code = $baseCode !== '' ? $baseCode : 'STORE';
        $candidate = $code;
        $n = 0;
        while (Store::withoutGlobalScope('team')->where('team_id', $targetTeamId)->where('code', $candidate)->exists())
        {
            $n++;
            $candidate = $code.'-'.$n;
        }

        return $candidate;
    }

    private function uniqueProductCode(?string $baseCode, int $targetTeamId, int $sourceProductId): string
    {
        $code = $baseCode !== null && trim($baseCode) !== '' ? trim($baseCode) : 'SKU-'.$sourceProductId;
        $candidate = $code;
        $n = 0;
        while (Product::withoutGlobalScope('team')->where('team_id', $targetTeamId)->where('code', $candidate)->exists())
        {
            $n++;
            $candidate = $code.'-c'.$n;
        }

        return $candidate;
    }

    private function normalizeSingleMainStoreFlag(int $targetTeamId): void
    {
        $mains = Store::withoutGlobalScope('team')
            ->where('team_id', $targetTeamId)
            ->where('is_main', true)
            ->orderBy('id')
            ->get();
        if ($mains->count() <= 1)
        {
            return;
        }
        $first = true;
        foreach ($mains as $row)
        {
            if ($first)
            {
                $first = false;

                continue;
            }
            Store::withoutGlobalScope('team')->whereKey($row->id)->update(['is_main' => false]);
        }
    }
}
