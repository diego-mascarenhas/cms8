<?php

namespace App\Http\Controllers\Api\Shop;

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

        return response()->json([
            'success' => true,
            'data' => [
                'products_count' => Product::query()->count(),
                'stores_count' => Store::query()->count(),
                'orders_count' => Order::query()->count(),
                'pending_orders' => Order::query()->where('payment_status', 'pending')->count(),
                'open_carts_count' => $this->shoppingCarts->countOpenForTeam((int) $team->id),
                'recent_orders' => $recentOrders,
            ],
        ]);
    }
}
