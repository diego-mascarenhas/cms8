<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\CfoBriefLauncher;
use App\Services\Finance\FinanceCfoBriefService;
use App\Services\Finance\FinanceDashboardSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceAnalyticsController extends Controller
{
    public function __construct(
        private readonly FinanceCfoBriefService $financeCfoBriefService,
        private readonly FinanceDashboardSnapshotService $financeDashboardSnapshotService,
        private readonly CfoBriefLauncher $cfoBriefLauncher,
    ) {}

    public function cashPosition(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        return response()->json(
            $this->financeDashboardSnapshotService->buildCashPosition($team),
        );
    }

    public function dashboardSnapshot(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $year = (int) $request->query('year', Carbon::now()->year);
        $year = $year > 0 ? $year : (int) Carbon::now()->year;
        $refresh = $request->boolean('refresh');

        if ($refresh)
        {
            $snapshot = $this->financeDashboardSnapshotService->capture($team, $year);
        } else
        {
            $snapshot = $this->financeDashboardSnapshotService->latest($team, $year);
        }

        if ($snapshot === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('Todavía no hay un snapshot financiero.'),
                'year' => $year,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'year' => $year,
            'captured_at' => $snapshot->captured_at?->toIso8601String(),
            'payload' => $snapshot->payload,
        ]);
    }

    public function flowProjection(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $year = (int) $request->query('year', Carbon::now()->year);
        $year = $year > 0 ? $year : (int) Carbon::now()->year;
        $projection = $this->financeCfoBriefService->projection($team, $year);

        return response()->json([
            'success' => true,
            'year' => $year,
            'reporting_currency' => (string) ($projection['currency'] ?? ''),
            'avg_monthly_income' => (float) ($projection['avg_monthly_income'] ?? 0),
            'avg_monthly_expense' => (float) ($projection['avg_monthly_expense'] ?? 0),
            'avg_monthly_profit' => (float) ($projection['avg_monthly_profit'] ?? 0),
            'chart_income' => (float) ($projection['chart_income'] ?? 0),
            'chart_expense' => (float) ($projection['chart_expense'] ?? 0),
            'year_profit' => (float) ($projection['year_profit'] ?? 0),
            'months_with_data' => (int) ($projection['months_with_data'] ?? 0),
            'contracted_income' => (float) ($projection['contracted_income'] ?? 0),
            'contracted_expense' => (float) ($projection['contracted_expense'] ?? 0),
            'points' => $projection['points'] ?? [],
        ]);
    }

    public function storeCfoBrief(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $year = (int) $request->input('year', Carbon::now()->year);
        $year = $year > 0 ? $year : (int) Carbon::now()->year;
        $started = $this->financeCfoBriefService->begin($team, $year);

        if ($started)
        {
            $this->cfoBriefLauncher->start($team->id, $year);
        }

        return response()->json([
            'success' => true,
            'started' => $started,
            'year' => $year,
            'run' => $this->financeCfoBriefService->runStatus($team, $year),
        ]);
    }

    public function cfoStatus(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $year = (int) $request->query('year', Carbon::now()->year);
        $year = $year > 0 ? $year : (int) Carbon::now()->year;

        return response()->json([
            'success' => true,
            'year' => $year,
            'run' => $this->financeCfoBriefService->runStatus($team, $year),
            'analysis' => $this->financeCfoBriefService->storedAnalysis($team, $year),
        ]);
    }
}
