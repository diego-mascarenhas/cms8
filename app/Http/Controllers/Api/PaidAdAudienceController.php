<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePaidAdAudienceApiRequest;
use App\Http\Requests\Api\UpdatePaidAdAudienceApiRequest;
use App\Models\PaidAdAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaidAdAudienceController extends Controller
{
    use ChecksTeamModule;

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
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = PaidAdAudience::query()
            ->where('team_id', $team->id)
            ->withCount('campaigns')
            ->orderBy('name');

        if ($search !== '')
        {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $paginator = $query->paginate($perPage);
        $paginator->setPath($request->url());
        $paginator->appends($request->query());

        $items = $paginator->getCollection()
            ->map(fn (PaidAdAudience $audience) => $this->format($audience))
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

        $audience = PaidAdAudience::query()
            ->where('team_id', $team->id)
            ->withCount('campaigns')
            ->find($id);

        if (! $audience)
        {
            return response()->json([
                'success' => false,
                'message' => __('Audience not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->format($audience),
        ]);
    }

    public function store(StorePaidAdAudienceApiRequest $request): JsonResponse
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
        $validated['team_id'] = $team->id;
        $validated['created_by'] = $request->user()->id;

        $audience = PaidAdAudience::query()->create($validated);
        $audience->loadCount('campaigns');

        return response()->json([
            'success' => true,
            'data' => $this->format($audience),
        ], 201);
    }

    public function update(UpdatePaidAdAudienceApiRequest $request, int $id): JsonResponse
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

        $audience = PaidAdAudience::query()->where('team_id', $team->id)->find($id);
        if (! $audience)
        {
            return response()->json([
                'success' => false,
                'message' => __('Audience not found'),
            ], 404);
        }

        $audience->update($request->validated());
        $audience->loadCount('campaigns');

        return response()->json([
            'success' => true,
            'data' => $this->format($audience),
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

        $audience = PaidAdAudience::query()->where('team_id', $team->id)->find($id);
        if (! $audience)
        {
            return response()->json([
                'success' => false,
                'message' => __('Audience not found'),
            ], 404);
        }

        $audience->delete();

        return response()->json([
            'success' => true,
            'message' => __('The record has been deleted.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(PaidAdAudience $audience): array
    {
        return [
            'id' => $audience->id,
            'name' => $audience->name,
            'type' => $audience->type,
            'targeting_rules' => $audience->targeting_rules ?? [],
            'estimated_size' => $audience->estimated_size,
            'campaigns_count' => (int) ($audience->campaigns_count ?? 0),
            'created_at' => optional($audience->created_at)?->toIso8601String(),
            'updated_at' => optional($audience->updated_at)?->toIso8601String(),
        ];
    }
}
