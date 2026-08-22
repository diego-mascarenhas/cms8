<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdCreativeFormat;
use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeletePaidAdCreativeAssetRequest;
use App\Http\Requests\ShowPaidAdCreativeAssetRequest;
use App\Http\Requests\StorePaidAdCreativeAssetRequest;
use App\Services\PaidAds\PaidAdCreativeAssetService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaidAdCreativeAssetController extends Controller
{
    use ChecksTeamModule;

    public function __construct(private readonly PaidAdCreativeAssetService $assets) {}

    public function show(ShowPaidAdCreativeAssetRequest $request): JsonResponse|StreamedResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'paid_ads'))
        {
            return $denied;
        }

        return $this->assets->stream($team, $request->validated('path'));
    }

    public function store(StorePaidAdCreativeAssetRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'paid_ads'))
        {
            return $denied;
        }

        $validated = $request->validated();
        $asset = $this->assets->store(
            $team,
            $validated['file'],
            AdCreativeFormat::from($validated['format']),
        );

        return response()->json([
            'success' => true,
            'data' => $asset,
        ], 201);
    }

    public function destroy(DeletePaidAdCreativeAssetRequest $request): JsonResponse
    {
        $team = $this->teamOrError($request);
        if ($team instanceof JsonResponse)
        {
            return $team;
        }

        if ($denied = $this->ensureTeamModule($team, 'paid_ads'))
        {
            return $denied;
        }

        $validated = $request->validated();

        $this->assets->delete($team, $validated['path']);

        return response()->json([
            'success' => true,
        ]);
    }
}
