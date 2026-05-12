<?php

namespace App\Http\Controllers;

use App\DataTables\UserDailyPerformanceInsightDataTable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class UserDailyPerformanceInsightController extends Controller
{
    public function index(UserDailyPerformanceInsightDataTable $dataTable): View|RedirectResponse
    {
        $this->authorize('viewAny', \App\Models\UserDailyPerformanceInsight::class);

        if (! auth()->user()->currentTeam)
        {
            return redirect()->route('error-without-team');
        }

        return $dataTable->render('user-daily-performance-insight.index');
    }
}
