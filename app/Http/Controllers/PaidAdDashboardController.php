<?php

namespace App\Http\Controllers;

use App\Enums\AdPlatform;
use App\Models\PaidAdCampaign;
use App\Services\PaidAdMetricsAggregator;
use Illuminate\Contracts\View\View;

class PaidAdDashboardController extends Controller
{
    public function __construct(private readonly PaidAdMetricsAggregator $aggregator) {}

    public function index(): View
    {
        $this->authorize('viewAny', PaidAdCampaign::class);
        $this->ensureModule();

        $teamId = auth()->user()->currentTeam->id;

        $totals = $this->aggregator->forTeam($teamId);
        $breakdown = $this->aggregator->breakdownByPlatform($teamId);

        foreach ($breakdown as &$row)
        {
            $platform = AdPlatform::tryFrom($row['platform']);
            $row['label'] = $platform?->label() ?? $row['platform'];
            $row['icon'] = $platform?->icon() ?? 'ti ti-target-arrow';
        }
        unset($row);

        $activeCampaigns = PaidAdCampaign::query()
            ->whereIn('status', ['active', 'publishing'])
            ->count();

        return view('paid-ads.dashboard', compact('totals', 'breakdown', 'activeCampaigns'));
    }

    private function ensureModule(): void
    {
        if (! auth()->user()?->currentTeam?->hasModule('paid_ads'))
        {
            abort(404);
        }
    }
}
