<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Finance\FinanceCfoBriefService;
use App\Services\WeeklyWorkPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StrategyController extends Controller
{
    public function index(Request $request, WeeklyWorkPlanService $plans): View
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);

        $currentLevel = $plans->strategyLevel($team);

        return view('strategy.index', [
            'steps' => config('strategy.steps', []),
            'currentLevel' => $currentLevel,
            'canAdvance' => $currentLevel < 12,
            'canEdit' => $user->can('update', $team),
        ]);
    }

    public function level(Request $request, WeeklyWorkPlanService $plans): View
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);
        $this->authorize('update', $team);

        $currentLevel = $plans->strategyLevel($team);

        return view('strategy.level', [
            'currentLevel' => $currentLevel,
            'currentStep' => $plans->strategyStep($team),
            'canAdvance' => $currentLevel < 12,
        ]);
    }

    public function analysis(Request $request, FinanceCfoBriefService $briefs): View
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);

        return view('strategy.analysis', [
            'cfoAnalysis' => $briefs->storedAnalysis($team, (int) now()->year),
            'canAskCfo' => $user->can('viewAny', Payment::class),
        ]);
    }

    public function update(Request $request, WeeklyWorkPlanService $plans): RedirectResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);
        $this->authorize('update', $team);

        $allowed = $plans->allowedStrategyFieldKeys();
        $rules = [];
        foreach ($allowed as $key)
        {
            $rules['strategy.'.$key] = ['nullable', 'string', 'max:5000'];
        }

        $validated = $request->validate($rules);
        $plans->saveStrategyFields($team, $validated['strategy'] ?? []);

        return redirect()
            ->route('strategy.level')
            ->with('success', __('app.weekly_plan_strategy_saved'));
    }

    public function advance(Request $request, WeeklyWorkPlanService $plans): RedirectResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);
        $this->authorize('update', $team);

        $level = $plans->advanceStrategyLevel($team);

        return redirect()
            ->route('strategy.level')
            ->with('success', __('app.weekly_plan_strategy_advanced', ['level' => $level]));
    }
}
