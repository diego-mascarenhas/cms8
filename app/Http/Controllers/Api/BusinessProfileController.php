<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteBusinessProfileAssetRequest;
use App\Http\Requests\ShowBusinessProfileAssetRequest;
use App\Http\Requests\StoreBusinessProfileAssetRequest;
use App\Http\Requests\UpdateBusinessProfileRequest;
use App\Services\Business\BusinessProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessProfileController extends Controller
{
    use ChecksTeamModule;

    public function __construct(private readonly BusinessProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return response()->json([
            'success' => true,
            'data' => $this->profiles->payload($team),
        ]);
    }

    public function update(UpdateBusinessProfileRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($request->user()?->cannot('update', $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No podés editar el negocio de este equipo.'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->profiles->update($team, $request->validated()),
        ]);
    }

    public function showAsset(ShowBusinessProfileAssetRequest $request): JsonResponse|StreamedResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        return $this->profiles->stream($team, $request->validated('path'));
    }

    public function storeAsset(StoreBusinessProfileAssetRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($request->user()?->cannot('update', $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No podés editar el negocio de este equipo.'),
            ], 403);
        }

        $validated = $request->validated();

        return response()->json([
            'success' => true,
            'data' => $this->profiles->storeAsset($team, $validated['file'], $validated['role']),
        ], 201);
    }

    public function destroyAsset(DeleteBusinessProfileAssetRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($request->user()?->cannot('update', $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No podés editar el negocio de este equipo.'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->profiles->deleteAsset($team, $request->validated('path')),
        ]);
    }

    public function generateSummary(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($request->user()?->cannot('update', $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No podés editar el negocio de este equipo.'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->profiles->generateSummary($team),
        ]);
    }

    public function queueInsights(Request $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($request->user()?->cannot('update', $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No podés editar el negocio de este equipo.'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->profiles->queueInsights($team),
        ]);
    }
}
