<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReorderMailerCategoriesRequest;
use App\Http\Requests\Api\StoreMailerCategoryRequest;
use App\Http\Requests\Api\UpdateMailerCategoryRequest;
use App\Http\Requests\Api\UpdateMailerCategorySortRequest;
use App\Models\Category;
use App\Models\Team;
use App\Services\MailerCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MailerCategoryController extends Controller
{
    use ChecksTeamModule;

    public function __construct(
        private readonly MailerCategoryService $categories,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $team = $this->readyTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return response()->json([
            'success' => true,
            'data' => $this->categories->presentForTeam($team),
        ]);
    }

    public function updateSort(UpdateMailerCategorySortRequest $request): JsonResponse
    {
        $team = $this->readyTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return response()->json([
            'success' => true,
            'data' => $this->categories->setSortForTeam(
                $team,
                (string) $request->validated('sort'),
            ),
        ]);
    }

    public function reorder(ReorderMailerCategoriesRequest $request): JsonResponse
    {
        $team = $this->readyTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
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

    public function store(StoreMailerCategoryRequest $request): JsonResponse
    {
        $team = $this->readyTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
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

    public function update(UpdateMailerCategoryRequest $request, Category $category): JsonResponse
    {
        $team = $this->readyTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
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
        $team = $this->readyTeam($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
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

    private function readyTeam(Request $request): Team|JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'mailer'))
        {
            return $denied;
        }

        if ($denied = $this->ensureTeamModule($team, 'contacts'))
        {
            return $denied;
        }

        return $team;
    }
}
