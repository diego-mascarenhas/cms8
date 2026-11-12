<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\FormatsShopResources;
use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendShopOrderWhatsAppQuoteRequest;
use App\Http\Requests\Api\StoreShopBriefOrderRequest;
use App\Http\Requests\Api\UpdateShopOrderRequest;
use App\Jobs\GenerateShopBriefOrderJob;
use App\Models\Order;
use App\Services\ShopOrderItemsService;
use App\Services\ShopOrderWhatsAppQuoteService;
use App\Support\SearchNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
            SearchNormalizer::applyColumnsNavbarConditions($query, ['order_number'], $search);
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

    public function storeFromBrief(StoreShopBriefOrderRequest $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'orders');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('create', Order::class);

        $token = Str::random(48);
        Cache::put(GenerateShopBriefOrderJob::cacheKey($token), [
            'status' => 'processing',
            'team_id' => $team->id,
        ], now()->addHour());

        GenerateShopBriefOrderJob::dispatchAfterResponse(
            $token,
            (int) $team->id,
            (string) $request->validated('brief'),
        );

        return response()->json([
            'success' => true,
            'status' => 'processing',
            'poll_token' => $token,
        ]);
    }

    public function briefStatus(Request $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'orders');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $this->authorize('viewAny', Order::class);

        $token = trim((string) $request->query('poll_token', ''));
        $state = $token !== '' ? Cache::get(GenerateShopBriefOrderJob::cacheKey($token)) : null;
        if (! is_array($state) || (int) ($state['team_id'] ?? 0) !== (int) $team->id)
        {
            return response()->json([
                'success' => false,
                'message' => __('Invalid or expired quote. Please generate a new estimate.'),
            ], 422);
        }

        $status = (string) ($state['status'] ?? 'processing');
        if ($status === 'failed')
        {
            return response()->json([
                'success' => false,
                'status' => 'failed',
                'message' => (string) ($state['message'] ?? __('Could not generate the estimate. Please try again.')),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'status' => $status,
            'order_id' => $state['order_id'] ?? null,
            'order_number' => $state['order_number'] ?? null,
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

        $validated = $request->validated();
        $items = $validated['items'] ?? null;
        unset($validated['items']);

        if ($validated !== [])
        {
            $order->update($validated);
        }

        if (is_array($items))
        {
            app(ShopOrderItemsService::class)->sync($order, $items);
        }

        $order->refresh()->load(['contact', 'currency', 'store']);

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order),
        ]);
    }

    public function sendWhatsAppQuote(SendShopOrderWhatsAppQuoteRequest $request, int $id): JsonResponse
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

        $user = $request->user();
        if (! $user)
        {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated.'),
            ], 401);
        }

        $sent = app(ShopOrderWhatsAppQuoteService::class)->send(
            $order,
            $user,
            $request->validated('items'),
        );

        $order->refresh()->load(['contact', 'currency', 'store']);

        return response()->json([
            'success' => true,
            'message' => __('Cotización enviada por WhatsApp.'),
            'data' => $this->formatOrder($order),
            'quote' => $sent,
        ]);
    }
}
