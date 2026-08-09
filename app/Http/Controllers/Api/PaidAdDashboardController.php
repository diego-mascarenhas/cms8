<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdPlatform;
use App\Http\Controllers\Api\Concerns\ChecksTeamModule;
use App\Http\Controllers\Controller;
use App\Models\PaidAdCampaign;
use App\Services\PaidAdMetricsAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaidAdDashboardController extends Controller
{
    use ChecksTeamModule;

    public function __construct(private readonly PaidAdMetricsAggregator $aggregator) {}

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

        $totals = $this->aggregator->forTeam($team->id);
        $breakdown = $this->aggregator->breakdownByPlatform($team->id);

        foreach ($breakdown as &$row)
        {
            $platform = AdPlatform::tryFrom($row['platform']);
            $row['label'] = $platform?->label() ?? $row['platform'];
            $row['color'] = $platform?->color() ?? '#666666';
        }
        unset($row);

        $activeCampaigns = PaidAdCampaign::query()
            ->where('team_id', $team->id)
            ->whereIn('status', ['active', 'publishing'])
            ->count();

        $draftCampaigns = PaidAdCampaign::query()
            ->where('team_id', $team->id)
            ->where('status', 'draft')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'totals' => $totals,
                'breakdown' => $breakdown,
                'active_campaigns' => $activeCampaigns,
                'draft_campaigns' => $draftCampaigns,
            ],
        ]);
    }
}
