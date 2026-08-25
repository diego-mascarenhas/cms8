<?php

namespace App\Http\Controllers\Api\Shop;

use App\Enums\ProductCatalogStatus;
use App\Http\Controllers\Api\Shop\Concerns\FormatsShopResources;
use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Services\ShoppingCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use FormatsShopResources;
    use ResolvesShopTeam;

    public function __construct(
        protected ShoppingCartService $shoppingCarts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $team = $this->shopTeamWithAnyModule($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        Store::ensureMainStoreForTeam((int) $team->id);

        $recentOrders = Order::query()
            ->with(['contact', 'currency', 'store'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Order $order): array => $this->formatOrder($order))
            ->values()
            ->all();

        $openCarts = $this->shoppingCarts->openCartsForTeam((int) $team->id);

        return response()->json([
            'success' => true,
            'data' => [
                'products_count' => Product::query()->count(),
                'published_products_count' => Product::query()
                    ->where('catalog_status', ProductCatalogStatus::Publish)
                    ->count(),
                'stores_count' => Store::query()->count(),
                'orders_count' => Order::query()->count(),
                'orders_this_month' => Order::query()
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
                'pending_orders' => Order::query()->where('payment_status', 'pending')->count(),
                'pending_orders_total' => round((float) Order::query()
                    ->where('payment_status', 'pending')
                    ->sum('total_amount'), 2),
                'open_carts_count' => $openCarts->count(),
                'open_carts_items' => (int) $openCarts->sum(fn ($cart): int => $cart->totalQuantity()),
                'open_carts_total' => round((float) $openCarts->sum(fn ($cart): float => $cart->totalAmount()), 2),
                'recent_orders' => $recentOrders,
            ],
        ]);
    }
}
