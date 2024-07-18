<?php

// app/Http/Controllers/Dashboard/Analytics.php
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

        // Generar proyectos de prueba
        $projects = collect([
            (object) [
                'id' => 1,
                'name' => 'Website SEO',
                'leader' => (object) ['name' => 'Eileen'],
                'team' => collect([
                    (object) ['name' => 'John Doe', 'avatar' => 'https://via.placeholder.com/24'],
                    (object) ['name' => 'Jane Doe', 'avatar' => 'https://via.placeholder.com/24'],
                    (object) ['name' => 'Sam Smith', 'avatar' => 'https://via.placeholder.com/24'],
                ]),
                'status' => (object) ['name' => 'In Progress', 'percentage' => 38, 'color' => 'primary'],
                'date' => '2021-05-10',
            ],
            (object) [
                'id' => 2,
                'name' => 'Social Banners',
                'leader' => (object) ['name' => 'Owen'],
                'team' => collect([
                    (object) ['name' => 'Alice', 'avatar' => 'https://via.placeholder.com/24'],
                    (object) ['name' => 'Bob', 'avatar' => 'https://via.placeholder.com/24'],
                ]),
                'status' => (object) ['name' => 'Completed', 'percentage' => 45, 'color' => 'success'],
                'date' => '2021-01-03',
            ],
            (object) [
                'id' => 3,
                'name' => 'Logo Designs',
                'leader' => (object) ['name' => 'Keith'],
                'team' => collect([
                    (object) ['name' => 'Eve', 'avatar' => 'https://via.placeholder.com/24'],
                    (object) ['name' => 'Charlie', 'avatar' => 'https://via.placeholder.com/24'],
                    (object) ['name' => 'David', 'avatar' => 'https://via.placeholder.com/24'],
                    (object) ['name' => 'Frank', 'avatar' => 'https://via.placeholder.com/24'],
                ]),
                'status' => (object) ['name' => 'Pending', 'percentage' => 92, 'color' => 'warning'],
                'date' => '2021-08-12',
            ],
            // Agrega más proyectos de prueba según sea necesario
        ]);

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
            'projects' => $projects,
        ];

        return view('content.dashboard.dashboards-analytics', $data);
    }
}
