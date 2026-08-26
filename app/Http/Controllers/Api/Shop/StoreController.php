<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\FormatsShopResources;
use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequest;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    use FormatsShopResources;
    use ResolvesShopTeam;

    public function index(Request $request): JsonResponse
    {
        $team = $this->shopTeamWithAnyModule($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        Store::ensureMainStoreForTeam((int) $team->id);

        $stores = Store::query()
            ->withCount('products')
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get()
            ->map(fn (Store $store): array => $this->formatStore($store))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $stores,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $team = $this->shopTeamWithAnyModule($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $store = Store::query()->withCount('products')->find($id);
        if (! $store)
        {
            return response()->json([
                'success' => false,
                'message' => __('Store not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatStore($store),
        ]);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $team = $this->shopTeamWithAnyModule($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        Store::ensureMainStoreForTeam((int) $team->id);

        $payload = $this->storePayload($request->validated(), null);
        $payload['team_id'] = $team->id;
        $payload['is_main'] = $request->boolean('is_main');
        $payload['status'] = $request->boolean('status');

        if ($payload['is_main'])
        {
            Store::withoutGlobalScope('team')
                ->where('team_id', $team->id)
                ->update(['is_main' => false]);
        }

        $store = Store::withoutGlobalScope('team')->create($payload);
        $store->loadCount('products');

        return response()->json([
            'success' => true,
            'data' => $this->formatStore($store),
        ], 201);
    }

    public function update(StoreRequest $request, int $id): JsonResponse
    {
        $team = $this->shopTeamWithAnyModule($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $store = Store::query()->find($id);
        if (! $store)
        {
            return response()->json([
                'success' => false,
                'message' => __('Store not found'),
            ], 404);
        }

        $payload = $this->storePayload($request->validated(), $store);
        $payload['is_main'] = $request->boolean('is_main');
        $payload['status'] = $request->boolean('status');

        if ($payload['is_main'])
        {
            Store::withoutGlobalScope('team')
                ->where('team_id', $team->id)
                ->where('id', '!=', $store->id)
                ->update(['is_main' => false]);
        }

        $store->update($payload);
        $store->loadCount('products');

        return response()->json([
            'success' => true,
            'data' => $this->formatStore($store),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $team = $this->shopTeamWithAnyModule($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $store = Store::query()->find($id);
        if (! $store)
        {
            return response()->json([
                'success' => false,
                'message' => __('Store not found'),
            ], 404);
        }

        if ($store->is_main)
        {
            return response()->json([
                'success' => false,
                'message' => __('Main store cannot be deleted.'),
            ], 422);
        }

        $store->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
