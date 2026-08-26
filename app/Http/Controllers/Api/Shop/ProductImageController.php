<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShopProductImageRequest;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;

class ProductImageController extends Controller
{
    use ResolvesShopTeam;

    public function store(StoreShopProductImageRequest $request, ProductImageService $images): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $validated = $request->validated();

        return response()->json([
            'success' => true,
            'data' => $images->store(
                $team,
                $request->file('file'),
                $validated['name'] ?? null,
            ),
        ], 201);
    }
}
