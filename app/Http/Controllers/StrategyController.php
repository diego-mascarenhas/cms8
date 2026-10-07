<?php

namespace App\Http\Controllers;

use App\Http\Requests\SuggestStrategyFieldRequest;
use App\Models\Payment;
use App\Services\Finance\FinanceCfoBriefService;
use App\Services\Marketing\CmoBriefService;
use App\Services\StrategyFieldSuggestionService;
use App\Services\StrategyLevelReviewService;
use App\Services\SubsistenceAlertService;
use App\Services\WeeklyAnalysisLauncher;
use App\Services\WeeklyWorkPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StrategyController extends Controller
{
    public function index(Request $request, WeeklyWorkPlanService $plans, StrategyLevelReviewService $reviews): View
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);

        return view('strategy.index', [
            'steps' => config('strategy.steps', []),
            'currentLevel' => $plans->strategyLevel($team),
            'strategyValues' => $plans->strategyFieldValues($team),
            'reviewApproved' => $reviews->approvedTexts($team),
            'canEdit' => $user->can('update', $team),
        ]);
    }

    public function level(Request $request, WeeklyWorkPlanService $plans, ?int $level = null): View
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);
        $this->authorize('update', $team);
        abort_if($level !== null && ($level < 1 || $level > 12), 404);

        return view('strategy.level', [
            'currentStep' => $plans->strategyStep($team, $level),
        ]);
    }

    public function review(Request $request, StrategyLevelReviewService $reviews): View
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);

        return view('strategy.review', [
            'review' => $reviews->stored($team),
            'canEdit' => $user->can('update', $team),
        ]);
    }

    public function evaluate(Request $request, StrategyLevelReviewService $reviews): RedirectResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);
        $this->authorize('update', $team);

        $review = $reviews->evaluate($team);

        if ($review === null)
        {
            return redirect()
                ->route('strategy.review')
                ->with('error', __('app.strategy_review_failed'));
        }

        return redirect()
            ->route('strategy.review')
            ->with('success', __('app.strategy_review_ready', ['level' => $review['level']]));
    }

    public function analysis(Request $request, FinanceCfoBriefService $briefs, SubsistenceAlertService $subsistence): View
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);

        $year = (int) now()->year;

        return view('strategy.analysis', [
            'cfoAnalysis' => $briefs->storedAnalysis($team, $year),
            'cmoAnalysis' => app(CmoBriefService::class)->storedAnalysis($team, $year),
            'projection' => $briefs->withSalaryForecast($briefs->storedProjection($team, $year), $team),
            'canAskCfo' => $user->can('viewAny', Payment::class),
            'subsistence' => $subsistence->forTeam($team),
        ]);
    }

    public function refreshAnalysis(Request $request, WeeklyAnalysisLauncher $launcher): RedirectResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);

        $launcher->start($team->id);

        return redirect()
            ->route('strategy.analysis')
            ->with('success', __('app.cfo_analysis_refresh_started'));
    }

    public function cmoBrief(Request $request, CmoBriefService $briefs): RedirectResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);

        $year = (int) $request->input('year', now()->year);
        $briefs->remember($team, $year > 0 ? $year : (int) now()->year, $request->boolean('refresh'));

        return redirect()
            ->route('strategy.analysis')
            ->with('success', __('app.cmo_analysis_ready'));
    }

    public function suggest(SuggestStrategyFieldRequest $request, StrategyFieldSuggestionService $suggestions): JsonResponse
    {
        $user = $request->user();
        $team = $user?->currentTeam ?? $user?->teams->first();

        abort_if($user === null || $team === null, 404);
        $this->authorize('update', $team);

        $suggestion = $suggestions->suggest(
            $team,
            (string) $request->validated('field'),
            (string) ($request->validated('draft') ?? ''),
            $request->validated('siblings') ?? [],
        );

        if ($suggestion === null)
        {
            return response()->json([
                'message' => __('app.strategy_field_suggestion_failed'),
            ], 422);
        }

        return response()->json([
            'field' => $request->validated('field'),
            'suggestion' => $suggestion,
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

        $level = (int) $request->input('level');
        $redirect = ($level >= 1 && $level <= 12)
            ? redirect()->route('strategy.level', ['level' => $level])
            : redirect()->route('strategy.level');

        return $redirect->with('success', __('app.weekly_plan_strategy_saved'));
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
