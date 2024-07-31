<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Host;
use App\Models\Payment;
use App\Models\Project;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $hosts = Host::where('type_id', 1)->orderBy('name', 'asc')->get();

        // Current date and time
        $now = Carbon::now(); // Obtener la fecha y hora actual

        // Current year and month
        $year = Carbon::now()->year;
        $month = Carbon::now()->month;

        // Dates for the current month (sin horas, solo fecha)
        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        // Dates for last month (sin horas, solo fecha)
        $startOfLastMonth = Carbon::createFromDate($year, $month - 1, 1)->startOfDay();
        $endOfLastMonth = Carbon::createFromDate($year, $month - 1, 1)->endOfMonth()->endOfDay();

        // Ajuste para el cambio de año (si estamos en enero)
        if ($month == 1) {
            $startOfLastMonth = Carbon::createFromDate($year - 1, 12, 1)->startOfDay();
            $endOfLastMonth = Carbon::createFromDate($year - 1, 12, 1)->endOfMonth()->endOfDay();
        }
        
        // Define the column to use for date calculations
        $dateColumn = 'created_at'; // or 'date'

        // Calculate current month's earnings
        $currentMonthEarnings = Payment::where('transaction_type', 'I')
            ->whereBetween($dateColumn, [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Calculate previous month's earnings
        $previousMonthEarnings = Payment::where('transaction_type', 'I')
            ->whereBetween($dateColumn, [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');

        // Calculate current month's expenses
        $currentMonthExpenses = Payment::where('transaction_type', 'E')
            ->whereBetween($dateColumn, [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Calculate previous month's expenses
        $previousMonthExpenses = Payment::where('transaction_type', 'E')
            ->whereBetween($dateColumn, [$startOfLastMonth, $endOfLastMonth])
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
        for ($i = 0; $i < $now->daysInMonth; $i++)
        {
            $date = $startOfMonth->clone()->addDays($i)->toDateString();
            $monthDays[$date] = isset($dailyEarnings[$date]) ? $dailyEarnings[$date]['total'] : 0;
        }

        // Projects
        $projects = Project::with(['leader', 'client'])
            ->whereIn('status', [1, 7, 9])
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get()
            ->map(function ($project)
            {
                return (object) [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client' => $project->client->name,
                    'leader' => $project->leader->name,
                    'status_label' => $project->status_label,
                    'date' => $project->created_at->format('Y-m-d'),
                    'dropdown' => '
                <div class="dropdown">
                    <button class="btn p-0" type="button" id="sourceVisits" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-dots-vertical ti-sm text-muted"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="sourceVisits">
                        <a class="dropdown-item" href="' . route('project.edit', $project->id) . '">Edit Project</a>
                    </div>
                </div>'
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
