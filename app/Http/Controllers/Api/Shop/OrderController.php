<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\FormatsShopResources;
use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateShopOrderRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use FormatsShopResources;
    use ResolvesShopTeam;

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
            'payment_status' => 'nullable|string|max:32',
            'delivery_status' => 'nullable|string|max:32',
            'store_id' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Order::query()->with(['contact', 'currency', 'store']);

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '')
        {
            $query->where('order_number', 'like', '%'.$search.'%');
        }

        if (! empty($validated['payment_status']))
        {
            $query->where('payment_status', $validated['payment_status']);
        }

        if (! empty($validated['delivery_status']))
        {
            $query->where('delivery_status', $validated['delivery_status']);
        }

        if (! empty($validated['store_id']))
        {
            $query->where('store_id', $validated['store_id']);
        }

        $paginator = $query->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 20));

        $items = $paginator->getCollection()
            ->map(fn (Order $order): array => $this->formatOrder($order))
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

        $order = Order::query()->with(['contact', 'currency', 'store'])->find($id);
        if (! $order)
        {
            return response()->json([
                'success' => false,
                'message' => __('Order not found'),
            ], 404);
        }

        $this->authorize('view', $order);

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order),
        ]);
    }

    public function update(UpdateShopOrderRequest $request, int $id): JsonResponse
    {
        $team = $this->shopTeam($request, 'orders');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $order = Order::query()->find($id);
        if (! $order)
        {
            return response()->json([
                'success' => false,
                'message' => __('Order not found'),
            ], 404);
        }

        $order->update($request->validated());
        $order->load(['contact', 'currency', 'store']);

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order),
        ]);
    }
}
