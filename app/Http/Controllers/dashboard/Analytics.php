<?php

namespace App\Http\Controllers\dashboard;

use App\DataTables\ProjectDataTable;
use App\Http\Controllers\Controller;
use App\Models\Host;
use App\Models\Payment;
use Carbon\Carbon;

class Analytics extends Controller
{
    public function index(ProjectDataTable $dataTable)
    {
        $hosts = Host::orderBy('name', 'asc')->get();

        // Fechas actuales y anteriores
        $now = Carbon::now();
        $startOfMonth = $now->clone()->startOfMonth();
        $startOfLastMonth = $now->clone()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->clone()->subMonth()->endOfMonth();

        // Calcular los ingresos del mes actual
        $currentMonthEarnings = Payment::where('transaction_type', 'I')
            ->whereBetween('date', [$startOfMonth, $now])
            ->sum('amount');

        // Calcular los ingresos del mes anterior
        $previousMonthEarnings = Payment::where('transaction_type', 'I')
            ->whereBetween('date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');

        // Calcular los egresos del mes actual
        $currentMonthExpenses = Payment::where('transaction_type', 'E')
            ->whereBetween('date', [$startOfMonth, $now])
            ->sum('amount');

        // Calcular los egresos del mes anterior
        $previousMonthExpenses = Payment::where('transaction_type', 'E')
            ->whereBetween('date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');

        // Calcular el profit del mes actual y anterior
        $currentMonthProfit = $currentMonthEarnings - $currentMonthExpenses;
        $previousMonthProfit = $previousMonthEarnings - $previousMonthExpenses;

        // Comparar los profits de ambos meses
        $profitDifference = $currentMonthProfit - $previousMonthProfit;
        $profitPercentage = $previousMonthProfit ? ($profitDifference / $previousMonthProfit) * 100 : 0;

        // Calcular los ingresos diarios del mes actual
        $dailyEarnings = Payment::selectRaw('DATE(date) as date, SUM(amount) as total')
            ->where('transaction_type', 'I')
            ->whereBetween('date', [$startOfMonth, $now])
            ->groupBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Crear un array con los ingresos diarios para el mes actual
        $monthDays = [];
        for ($i = 0; $i < $now->daysInMonth; $i++) {
            $date = $startOfMonth->clone()->addDays($i)->toDateString();
            $monthDays[$date] = isset($dailyEarnings[$date]) ? $dailyEarnings[$date]['total'] : 0;
        }

        // Datos para pasar a la vista
        $data = [
            'hosts' => $hosts,
            'currentMonthEarnings' => $currentMonthEarnings,
            'previousMonthEarnings' => $previousMonthEarnings,
            'currentMonthExpenses' => $currentMonthExpenses,
            'currentMonthProfit' => $currentMonthProfit,
            'profitDifference' => $profitDifference,
            'profitPercentage' => $profitPercentage,
            'monthDays' => $monthDays,
        ];

        return $dataTable->render('content.dashboard.dashboards-analytics', $data);
    }
}
