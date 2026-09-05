<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PublicShopCheckoutRequest;
use App\Http\Requests\Api\PublicShopSyncCartRequest;
use App\Models\Team;
use App\Services\PublicShopCheckoutService;
use App\Services\ShoppingCartService;
use Illuminate\Http\JsonResponse;

class PublicShopCheckoutController extends Controller
{
    public function __construct(
        protected PublicShopCheckoutService $checkout,
        protected ShoppingCartService $carts,
    ) {}

    public function syncCart(PublicShopSyncCartRequest $request, string $slug): JsonResponse
    {
        $team = Team::findByCatalogSlug($slug);
        if (! $team)
        {
            return $this->shopNotFound();
        }

        $validated = $request->validated();
        $cart = $this->checkout->syncCart(
            $team,
            (string) $validated['guest_id'],
            $validated['items'],
        );

        return response()->json([
            'success' => true,
            'data' => [
                'cart_id' => $cart->id,
                'lines' => $this->carts->quantity($cart),
                'total' => $this->carts->total($cart),
            ],
        ]);
    }

    public function checkout(PublicShopCheckoutRequest $request, string $slug): JsonResponse
    {
        $team = Team::findByCatalogSlug($slug);
        if (! $team)
        {
            return $this->shopNotFound();
        }

        $validated = $request->validated();
        $result = $this->checkout->checkout(
            $team,
            (string) $validated['guest_id'],
            $validated['items'],
            isset($validated['customer_name']) ? trim((string) $validated['customer_name']) : null,
            isset($validated['customer_phone']) ? trim((string) $validated['customer_phone']) : null,
            isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            isset($validated['store_id']) ? (int) $validated['store_id'] : null,
            isset($validated['fulfillment_type']) ? trim((string) $validated['fulfillment_type']) : null,
            isset($validated['payment_method']) ? trim((string) $validated['payment_method']) : null,
            isset($validated['coupon_code']) ? trim((string) $validated['coupon_code']) : null,
            isset($validated['delivery_address']) ? trim((string) $validated['delivery_address']) : null,
        );

        $order = $result['order'];

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => (float) $order->total_amount,
                'whatsapp_url' => $result['whatsapp_url'],
                'whatsapp_text' => $result['whatsapp_text'],
            ],
        ]);
    }

    private function shopNotFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Shop not found.'),
        ], 404);
    }
}
