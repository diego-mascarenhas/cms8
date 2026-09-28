<?php

namespace App\Http\Controllers;

use App\Services\WeeklyWorkPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeeklyWorkPlanController extends Controller
{
    public function index(Request $request, WeeklyWorkPlanService $plans): View
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);

        $report = $plans->report($user, $team, $request->query('week'));

        return view('weekly-plan.index', [
            'report' => $report,
            'canRegenerate' => app()->environment('local'),
        ]);
    }

    public function regenerate(Request $request, WeeklyWorkPlanService $plans): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);

        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);

        $plans->present($user, $team, now(), forceRebuild: true);

        return redirect()
            ->route('weekly-plan.index')
            ->with('success', __('app.weekly_plan_regenerated'));
    }
}
