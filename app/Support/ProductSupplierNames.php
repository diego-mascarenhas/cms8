<?php

namespace App\Support;

use App\Models\SupplierPriceLine;

class ProductSupplierNames
{
    /**
     * Supplier name for each product, from its latest price-list line.
     *
     * @param  list<int>  $productIds
     * @return array<int, string>
     */
    public static function forProducts(int $teamId, array $productIds): array
    {
        $names = [];

        foreach (self::latestForProducts($teamId, $productIds) as $productId => $row)
        {
            if ($row['name'] !== '')
            {
                $names[$productId] = $row['name'];
            }
        }

        return $names;
    }

    /**
     * Latest supplier line for each product.
     *
     * @param  list<int>  $productIds
     * @return array<int, array{name: string, unit_cost: ?float}>
     */
    public static function latestForProducts(int $teamId, array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $productIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($teamId < 1 || $ids === [])
        {
            return [];
        }

        $rows = [];

        $lines = SupplierPriceLine::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->whereIn('product_id', $ids)
            ->with(['list' => function ($query): void
            {
                $query->withoutGlobalScope('team')->with('enterprise');
            }])
            ->orderBy('id')
            ->get();

        foreach ($lines as $line)
        {
            $productId = (int) $line->product_id;
            if ($productId < 1)
            {
                continue;
            }

            $rows[$productId] = [
                'name' => trim((string) ($line->list?->enterprise?->name ?? '')),
                'unit_cost' => $line->unit_cost !== null ? (float) $line->unit_cost : null,
            ];
        }

        return $rows;
    }
}
