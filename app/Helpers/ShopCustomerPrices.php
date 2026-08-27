<?php

namespace App\Helpers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Services\WhatsAppCheckoutOrderService;

/**
 * Customer-facing catalog prices follow each store «Mostrar precios» flag.
 * The shop quote button is the explicit exception and does not use this helper.
 */
class ShopCustomerPrices
{
    public static function storeShows(?Store $store): bool
    {
        return $store === null || $store->showsPrices();
    }

    public static function productShows(Product $product): bool
    {
        return $product->catalogShowsPrice();
    }

    public static function orderShows(Order $order): bool
    {
        $order->loadMissing('store');

        return self::storeShows($order->store);
    }

    /**
     * @param  iterable<object>  $cartItems
     */
    public static function cartShows(int $teamId, iterable $cartItems): bool
    {
        $store = app(WhatsAppCheckoutOrderService::class)->resolveStoreForCart($teamId, $cartItems);

        return self::storeShows($store);
    }
}
