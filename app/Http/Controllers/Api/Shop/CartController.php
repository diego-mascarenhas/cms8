<?php

namespace App\Http\Controllers\Api\Shop;

use App\Enums\ShoppingCartChannel;
use App\Http\Controllers\Api\Shop\Concerns\FormatsShopResources;
use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShoppingCart;
use App\Services\OpenCartListingService;
use App\Services\ShoppingCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    use FormatsShopResources;
    use ResolvesShopTeam;

    public function __construct(
        protected ShoppingCartService $shoppingCarts,
        protected OpenCartListingService $listing,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'orders');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('viewAny', Order::class);

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'channel' => ['nullable', 'string', Rule::enum(ShoppingCartChannel::class)],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $this->shoppingCarts->openCartsQuery((int) $team->id)
            ->with(['items' => fn ($items) => $items->withoutGlobalScope('team')])
            ->orderByDesc('updated_at');

        if (! empty($validated['channel']))
        {
            $query->where('channel', $validated['channel']);
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '')
        {
            $query->where(function ($carts) use ($search)
            {
                $carts->where('session_key', 'like', '%'.$search.'%')
                    ->orWhereHas('items', function ($items) use ($search)
                    {
                        $items->withoutGlobalScope('team')
                            ->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        $paginator = $query->paginate((int) ($validated['per_page'] ?? 20));

        $items = $paginator->getCollection()
            ->map(fn (ShoppingCart $cart): array => $this->listing->toApiArray($cart))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $team = $this->shopTeam($request, 'orders');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('viewAny', Order::class);

        $cart = $this->shoppingCarts->findForTeam((int) $team->id, $id);
        if (! $cart)
        {
            return response()->json([
                'success' => false,
                'message' => __('Cart not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->listing->toApiArray($cart, true),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $team = $this->shopTeam($request, 'orders');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('viewAny', Order::class);

        $cart = $this->shoppingCarts->findForTeam((int) $team->id, $id);
        if (! $cart)
        {
            return response()->json([
                'success' => false,
                'message' => __('Cart not found'),
            ], 404);
        }

        $this->shoppingCarts->clear($cart);
        $cart->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
