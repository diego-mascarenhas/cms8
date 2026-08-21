<?php

namespace App\Http\Controllers\Api\Shop\Concerns;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait FormatsShopResources
{
    /**
     * @return array<string, int>
     */
    protected function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'price' => (float) $product->price,
            'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
            'selling_price' => $product->currentSellingPrice(),
            'currency_id' => $product->currency_id,
            'currency' => $product->currency ? [
                'id' => $product->currency->id,
                'code' => $product->currency->code,
                'symbol' => $product->currency->symbol,
                'name' => $product->currency->name,
            ] : null,
            'store_id' => $product->store_id,
            'store' => $product->store ? [
                'id' => $product->store->id,
                'name' => $product->store->name,
            ] : null,
            'category_id' => $product->category_id,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,
            'catalog_status' => $product->catalog_status?->value,
            'catalog_status_label' => $product->catalog_status?->label(),
            'stock_status' => $product->stock_status?->value,
            'stock_status_label' => $product->stock_status?->label(),
            'manage_stock' => (bool) $product->manage_stock,
            'stock_quantity' => $product->stock_quantity,
            'size_options' => $product->size_options ?? [],
            'color_options' => $product->color_options ?? [],
            'whatsapp_enabled' => (bool) $product->whatsapp_enabled,
            'image' => $product->image,
            'status' => (bool) $product->status,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatStore(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'code' => $store->code,
            'address' => $store->address,
            'status' => (bool) $store->status,
            'is_main' => (bool) $store->is_main,
            'products_count' => $store->products_count ?? $store->products()->count(),
            'checkout_payment_methods' => $store->enabledCheckoutPaymentMethods(),
            'checkout_fulfillment_types' => $store->enabledCheckoutFulfillmentTypes(),
            'updated_at' => $store->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatOrder(Order $order): array
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'total_amount' => (float) $order->total_amount,
            'currency_id' => $order->currency_id,
            'currency' => $order->currency ? [
                'id' => $order->currency->id,
                'code' => $order->currency->code,
                'symbol' => $order->currency->symbol,
            ] : null,
            'store_id' => $order->store_id,
            'store' => $order->store ? [
                'id' => $order->store->id,
                'name' => $order->store->name,
            ] : null,
            'contact_id' => $order->contact_id,
            'contact' => $order->contact ? [
                'id' => $order->contact->id,
                'name' => $order->contact->name,
            ] : null,
            'payment_status' => $order->payment_status,
            'payment_status_label' => $order->payment_status_label,
            'delivery_status' => $order->delivery_status,
            'delivery_status_label' => $order->delivery_status_label,
            'notes' => $order->notes,
            'items' => is_array($metadata['items'] ?? null) ? $metadata['items'] : [],
            'metadata' => $metadata,
            'checkout_payment_method_labels' => $order->checkoutPaymentMethodDisplayLabels(),
            'checkout_fulfillment_labels' => $order->checkoutFulfillmentDisplayLabels(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function productPayload(array $validated): array
    {
        $manageStock = (bool) (int) $validated['manage_stock'];

        return [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? '',
            'short_description' => $validated['short_description'] ?? null,
            'price' => $validated['price'],
            'sale_price' => $validated['sale_price'] ?? null,
            'currency_id' => (int) $validated['currency_id'],
            'store_id' => isset($validated['store_id']) && $validated['store_id'] !== '' ? (int) $validated['store_id'] : null,
            'category_id' => (int) $validated['category_id'],
            'catalog_status' => $validated['catalog_status'],
            'stock_status' => $validated['stock_status'],
            'manage_stock' => $manageStock,
            'stock_quantity' => $manageStock ? (int) $validated['stock_quantity'] : null,
            'size_options' => $validated['size_options'] ?? [],
            'color_options' => $validated['color_options'] ?? [],
            'whatsapp_enabled' => (bool) (int) $validated['whatsapp_enabled'],
            'image' => $validated['image'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function storePayload(array $validated, ?Store $existing): array
    {
        $payment = array_values(array_intersect(
            Store::checkoutPaymentMethodKeys(),
            $validated['checkout_payment_methods'] ?? [],
        ));
        $fulfillment = array_values(array_intersect(
            Store::checkoutFulfillmentKeys(),
            $validated['checkout_fulfillment_types'] ?? [],
        ));
        unset($validated['checkout_payment_methods'], $validated['checkout_fulfillment_types']);

        $data = is_array($existing?->data) ? $existing->data : [];
        $data['checkout'] = [
            'payment_methods' => $payment,
            'fulfillment_types' => $fulfillment,
        ];
        $validated['data'] = $data;

        return $validated;
    }
}
