<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShopBrandRequest;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    use ResolvesShopTeam;

    public function store(StoreShopBrandRequest $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $name = trim((string) $request->validated('name'));
        $normalized = mb_strtolower($name);

        $existing = Brand::query()
            ->where('team_id', $team->id)
            ->get()
            ->first(fn (Brand $brand): bool => mb_strtolower(trim($brand->name)) === $normalized);

        if ($existing)
        {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $existing->id,
                    'name' => $existing->name,
                ],
            ]);
        }

        $brand = Brand::query()->create([
            'team_id' => $team->id,
            'name' => $name,
            'slug' => Str::slug($name) ?: null,
            'status' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $brand->id,
                'name' => $brand->name,
            ],
        ], 201);
    }
}
