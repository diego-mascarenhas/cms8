<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaidAdCampaignStatus;
use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaidAdCampaignRequest;
use App\Http\Requests\UpdatePaidAdCampaignRequest;
use App\Jobs\PublishPaidAdCampaignJob;
use App\Jobs\SyncPaidAdMetricsJob;
use App\Services\PaidAds\PaidAdCampaignApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaidAdCampaignController extends Controller
{
    use ChecksTeamModule;

    public function __construct(private readonly PaidAdCampaignApiService $campaigns) {}

    public function index(Request $request): JsonResponse
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

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? PaidAdCampaignApiService::PER_PAGE);

        $paginator = $this->campaigns->paginate($team, $search, $page, $perPage);
        $paginator->setPath($request->url());
        $paginator->appends($request->query());

        $items = $paginator->getCollection()
            ->map(fn ($campaign) => $this->campaigns->formatForList($campaign))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
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

        $campaign = $this->campaigns->findForTeam($team, $id);
        if (! $campaign)
        {
            return response()->json([
                'success' => false,
                'message' => __('Campaign not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->campaigns->formatForShow($campaign),
        ]);
    }

    public function store(StorePaidAdCampaignRequest $request): JsonResponse
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
        $platformConnectionIds = $validated['platforms'] ?? [];
        $audienceIds = $validated['audiences'] ?? [];
        unset($validated['platforms'], $validated['audiences'], $validated['status']);

        $campaign = $this->campaigns->create(
            $team,
            (int) $request->user()->id,
            $validated,
            $platformConnectionIds,
            $audienceIds,
        );

        return response()->json([
            'success' => true,
            'data' => $this->campaigns->formatForShow($campaign),
        ], 201);
    }

    public function update(UpdatePaidAdCampaignRequest $request, int $id): JsonResponse
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

        $campaign = $this->campaigns->findForTeam($team, $id);
        if (! $campaign)
        {
            return response()->json([
                'success' => false,
                'message' => __('Campaign not found'),
            ], 404);
        }

        $validated = $request->validated();
        $platformConnectionIds = $validated['platforms'] ?? [];
        $audienceIds = $validated['audiences'] ?? [];
        unset($validated['platforms'], $validated['audiences'], $validated['status']);

        $campaign = $this->campaigns->update($campaign, $team, $validated, $platformConnectionIds, $audienceIds);

        return response()->json([
            'success' => true,
            'data' => $this->campaigns->formatForShow($campaign),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
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

        $campaign = $this->campaigns->findForTeam($team, $id);
        if (! $campaign)
        {
            return response()->json([
                'success' => false,
                'message' => __('Campaign not found'),
            ], 404);
        }

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => __('The record has been deleted.'),
        ]);
    }

    public function publish(Request $request, int $id): JsonResponse
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

        $campaign = $this->campaigns->findForTeam($team, $id);
        if (! $campaign)
        {
            return response()->json([
                'success' => false,
                'message' => __('Campaign not found'),
            ], 404);
        }

        if ($campaign->platforms->isEmpty())
        {
            return response()->json([
                'success' => false,
                'message' => __('Add at least one connected platform before publishing.'),
            ], 422);
        }

        $campaign->forceFill(['status' => PaidAdCampaignStatus::Publishing])->save();
        PublishPaidAdCampaignJob::dispatch($campaign->id);

        return response()->json([
            'success' => true,
            'message' => __('Publishing started. Platform statuses will update shortly.'),
            'data' => $this->campaigns->formatForShow($this->campaigns->findForTeam($team, $id)),
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        return $this->setStatus($request, $id, PaidAdCampaignStatus::Paused, __('Campaign paused.'));
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        return $this->setStatus($request, $id, PaidAdCampaignStatus::Active, __('Campaign resumed.'));
    }

    public function syncMetrics(Request $request, int $id): JsonResponse
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

        $campaign = $this->campaigns->findForTeam($team, $id);
        if (! $campaign)
        {
            return response()->json([
                'success' => false,
                'message' => __('Campaign not found'),
            ], 404);
        }

        foreach ($campaign->platforms as $campaignPlatform)
        {
            SyncPaidAdMetricsJob::dispatch($campaignPlatform->id);
        }

        return response()->json([
            'success' => true,
            'message' => __('Metric sync queued.'),
        ]);
    }

    public function lookups(Request $request): JsonResponse
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

        return response()->json([
            'success' => true,
            'data' => $this->campaigns->lookups($team),
        ]);
    }

    private function setStatus(Request $request, int $id, PaidAdCampaignStatus $status, string $message): JsonResponse
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

        $campaign = $this->campaigns->findForTeam($team, $id);
        if (! $campaign)
        {
            return response()->json([
                'success' => false,
                'message' => __('Campaign not found'),
            ], 404);
        }

        $campaign->forceFill(['status' => $status])->save();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->campaigns->formatForShow($this->campaigns->findForTeam($team, $id)),
        ]);
    }
}
