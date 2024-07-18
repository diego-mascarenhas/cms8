<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Host;
use App\Models\Payment;
use App\Models\Project;
use Carbon\Carbon;

class Analytics extends Controller
{
    public function index()
    {
        $hosts = Host::orderBy('name', 'asc')->get();

        // Current and previous dates
        $now = Carbon::now();
        $startOfMonth = $now->clone()->startOfMonth();
        $startOfLastMonth = $now->clone()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->clone()->subMonth()->endOfMonth();

        // Calculate current month's earnings
        $currentMonthEarnings = Payment::where('transaction_type', 'I')
            ->whereBetween('date', [$startOfMonth, $now])
            ->sum('amount');

        // Calculate previous month's earnings
        $previousMonthEarnings = Payment::where('transaction_type', 'I')
            ->whereBetween('date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');

        // Calculate current month's expenses
        $currentMonthExpenses = Payment::where('transaction_type', 'E')
            ->whereBetween('date', [$startOfMonth, $now])
            ->sum('amount');

        // Calculate previous month's expenses
        $previousMonthExpenses = Payment::where('transaction_type', 'E')
            ->whereBetween('date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');

        // Calculate current and previous month's profit
        $currentMonthProfit = $currentMonthEarnings - $currentMonthExpenses;
        $previousMonthProfit = $previousMonthEarnings - $previousMonthExpenses;

        // Compare profits of both months
        $profitDifference = $currentMonthProfit - $previousMonthProfit;
        $profitPercentage = $previousMonthProfit ? ($profitDifference / $previousMonthProfit) * 100 : 0;

        // Calculate daily earnings for the current month
        $dailyEarnings = Payment::selectRaw('DATE(date) as date, SUM(amount) as total')
            ->where('transaction_type', 'I')
            ->whereBetween('date', [$startOfMonth, $now])
            ->groupBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Create an array with daily earnings for the current month
        $monthDays = [];
        for ($i = 0; $i < $now->daysInMonth; $i++) {
            $date = $startOfMonth->clone()->addDays($i)->toDateString();
            $monthDays[$date] = isset($dailyEarnings[$date]) ? $dailyEarnings[$date]['total'] : 0;
        }

        $projects = Project::with(['leader', 'client'])
            ->whereIn('status', [1, 7, 9])
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get()
            ->map(function ($project) {
                return (object) [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client' => $project->client->name,
                    'leader' => $project->leader->name,
                    'status' => (object) [
                        'name' => 'test',
                        'percentage' => 10,
                        'color' => 'success'
                    ],
                    'date' => $project->created_at->format('Y-m-d')
                ];
            });

        $data = [
            'hosts' => $hosts,
            'currentMonthEarnings' => $currentMonthEarnings,
            'previousMonthEarnings' => $previousMonthEarnings,
            'currentMonthExpenses' => $currentMonthExpenses,
            'currentMonthProfit' => $currentMonthProfit,
            'profitDifference' => $profitDifference,
            'profitPercentage' => $profitPercentage,
            'monthDays' => $monthDays,
            'projects' => $projects,
        ];

        return view('content.dashboard.dashboards-analytics', $data);
    }
}
