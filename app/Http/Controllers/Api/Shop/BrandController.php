<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShopBrandLogoRequest;
use App\Http\Requests\Api\StoreShopBrandRequest;
use App\Http\Requests\Api\UpdateShopBrandRequest;
use App\Models\Brand;
use App\Services\BrandLogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
{
    use ResolvesShopTeam;

    public function index(Request $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $brands = Brand::query()
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $brands->map(fn (Brand $brand): array => $this->formatBrand($brand))->values()->all(),
        ]);
    }

    public function store(StoreShopBrandRequest $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $name = trim((string) $request->validated('name'));
        $existing = $this->findByName($team->id, $name);

        if ($existing)
        {
            return response()->json([
                'success' => true,
                'data' => $this->formatBrand($existing->loadCount('products')),
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
            'data' => $this->formatBrand($brand->loadCount('products')),
        ], 201);
    }

    public function update(UpdateShopBrandRequest $request, int $id): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $brand = $this->teamBrand($team->id, $id);
        if ($brand instanceof JsonResponse)
        {
            return $brand;
        }

        $name = trim((string) $request->validated('name'));
        $duplicate = $this->findByName($team->id, $name);
        if ($duplicate && $duplicate->id !== $brand->id)
        {
            throw ValidationException::withMessages([
                'name' => [__('Ya existe una marca con ese nombre.')],
            ]);
        }

        $brand->forceFill([
            'name' => $name,
            'slug' => Str::slug($name) ?: $brand->slug,
        ])->save();

        return response()->json([
            'success' => true,
            'data' => $this->formatBrand($brand->loadCount('products')),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $brand = $this->teamBrand($team->id, $id);
        if ($brand instanceof JsonResponse)
        {
            return $brand;
        }

        $brand->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function logo(StoreShopBrandLogoRequest $request, int $id, BrandLogoService $logos): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $brand = $this->teamBrand($team->id, $id);
        if ($brand instanceof JsonResponse)
        {
            return $brand;
        }

        $logos->store($team, $brand, $request->file('file'));

        return response()->json([
            'success' => true,
            'data' => $this->formatBrand($brand->fresh()->loadCount('products')),
        ]);
    }

    /**
     * @return array{id: int, name: string, logo: string|null, products_count: int}
     */
    private function formatBrand(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'logo' => $brand->logo,
            'products_count' => (int) ($brand->products_count ?? 0),
        ];
    }

    private function findByName(int $teamId, string $name): ?Brand
    {
        $normalized = mb_strtolower(trim($name));

        return Brand::query()
            ->where('team_id', $teamId)
            ->get()
            ->first(fn (Brand $brand): bool => mb_strtolower(trim($brand->name)) === $normalized);
    }

    private function teamBrand(int $teamId, int $id): Brand|JsonResponse
    {
        $brand = Brand::query()
            ->where('team_id', $teamId)
            ->find($id);

        if (! $brand)
        {
            return response()->json([
                'success' => false,
                'message' => __('Marca no encontrada.'),
            ], 404);
        }

        return $brand;
    }
}
