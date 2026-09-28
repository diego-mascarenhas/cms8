<?php

namespace App\Http\Controllers;

use App\Services\WeeklyWorkPlanService;
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
        ]);
    }
}
