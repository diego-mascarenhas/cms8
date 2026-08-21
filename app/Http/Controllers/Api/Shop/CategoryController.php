<?php

namespace App\Http\Controllers\Api\Shop;

use App\Http\Controllers\Api\Shop\Concerns\ResolvesShopTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShopCategoryRequest;
use App\Models\Category;
use App\Models\Module;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ResolvesShopTeam;

    public function store(StoreShopCategoryRequest $request): JsonResponse
    {
        $team = $this->shopTeam($request, 'products');
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        $moduleId = Module::query()->where('key', 'products')->value('id');
        if (! $moduleId)
        {
            return response()->json([
                'success' => false,
                'message' => __('The products module is not available.'),
            ], 422);
        }

        $name = trim((string) $request->validated('name'));
        $normalized = mb_strtolower($name);

        $existing = Category::query()
            ->where('team_id', $team->id)
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at')
            ->get()
            ->first(fn (Category $category): bool => mb_strtolower(trim($category->name)) === $normalized);

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

        $category = Category::query()->create([
            'name' => $name,
            'module_id' => $moduleId,
            'team_id' => $team->id,
            'parent_id' => null,
            'order' => 0,
            'status' => 1,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ], 201);
    }
}
