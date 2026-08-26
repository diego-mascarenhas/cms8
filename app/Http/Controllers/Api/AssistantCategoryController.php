<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderAssistantCategoriesRequest;
use App\Http\Requests\StoreAssistantCategoryRequest;
use App\Http\Requests\UpdateAssistantCategoryRequest;
use App\Http\Requests\UpdateAssistantCategorySortRequest;
use App\Models\Category;
use App\Services\WhatsApp\WhatsAppThreadCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AssistantCategoryController extends Controller
{
    public function __construct(
        private readonly WhatsAppThreadCategoryService $categories,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->categories->presentForTeam($team),
        ]);
    }

    public function updateSort(UpdateAssistantCategorySortRequest $request): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->categories->setSortForTeam(
                $team,
                (string) $request->validated('sort'),
            ),
        ]);
    }

    public function reorder(ReorderAssistantCategoriesRequest $request): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        try
        {
            $data = $this->categories->reorderForTeam($team, $request->validated('ids'));
        } catch (InvalidArgumentException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => __($exception->getMessage()),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(StoreAssistantCategoryRequest $request): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        try
        {
            $category = $this->categories->createForTeam(
                $team,
                (string) $request->validated('name'),
                $request->validated('color'),
            );
        } catch (InvalidArgumentException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => __($exception->getMessage()),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $category,
        ], 201);
    }

    public function update(UpdateAssistantCategoryRequest $request, Category $category): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        try
        {
            $updated = $this->categories->updateForTeam($team, $category, $request->validated());
        } catch (InvalidArgumentException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => __($exception->getMessage()),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $team = $request->user()?->currentTeam;
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        try
        {
            $this->categories->deleteForTeam($team, $category);
        } catch (InvalidArgumentException $exception)
        {
            return response()->json([
                'success' => false,
                'message' => __($exception->getMessage()),
            ], 404);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
