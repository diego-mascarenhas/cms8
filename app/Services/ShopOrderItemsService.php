<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;

class ShopOrderItemsService
{
    /**
     * Replace order line items and recompute the total.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function sync(Order $order, array $items): Order
    {
        $normalized = [];

        foreach ($items as $row)
        {
            if (! is_array($row))
            {
                continue;
            }

            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            $product = $productId > 0
                ? Product::withoutGlobalScope('team')
                    ->where('team_id', $order->team_id)
                    ->with('category')
                    ->find($productId)
                : null;

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '')
            {
                $name = $product?->name ?: 'Producto';
            }

            if (array_key_exists('unit_price', $row) && $row['unit_price'] !== null && $row['unit_price'] !== '')
            {
                $unitPrice = (float) $row['unit_price'];
            } else
            {
                $unitPrice = $product ? (float) $product->currentSellingPrice() : 0.0;
            }

            $normalized[] = [
                'product_id' => $product?->id ?? ($productId > 0 ? $productId : null),
                'name' => $name,
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, 2),
                'line_total' => round($unitPrice * $quantity, 2),
                'category_name' => $product?->category?->name
                    ?? (isset($row['category_name']) ? (string) $row['category_name'] : null),
                'currency_id' => $product?->currency_id ?? $order->currency_id,
            ];
        }

        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $metadata['items'] = $normalized;

        $currencyId = $order->currency_id;
        foreach ($normalized as $line)
        {
            if (! empty($line['currency_id']))
            {
                $currencyId = (int) $line['currency_id'];

                break;
            }
        }

        $order->update([
            'metadata' => $metadata,
            'total_amount' => round(array_sum(array_map(
                fn (array $line): float => (float) $line['line_total'],
                $normalized,
            )), 2),
            'currency_id' => $currencyId,
        ]);

        return $order;
    }
}
