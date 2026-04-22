<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinancialDashboardController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = Carbon::now()->year;
        $requestedYear = (int) $request->query('year', $currentYear);
        $selectedYear = $requestedYear > 0 ? $requestedYear : $currentYear;

        $minYear = (int) (Payment::whereNotNull('date')
            ->selectRaw('MIN(EXTRACT(YEAR FROM "date")) as year_value')
            ->value('year_value') ?? 0);
        $maxYear = (int) (Payment::whereNotNull('date')
            ->selectRaw('MAX(EXTRACT(YEAR FROM "date")) as year_value')
            ->value('year_value') ?? 0);

        if ($minYear === 0 || $maxYear === 0)
        {
            $minYear = $currentYear;
            $maxYear = $currentYear;
        }

        $minYear = min($minYear, $currentYear);
        $maxYear = max($maxYear, $currentYear);
        $selectedYear = max($minYear, min($selectedYear, $maxYear));
        $availableYears = range($maxYear, $minYear);

        // Get accounts with balances
        $accounts = PaymentAccount::with('currency')
            ->get()
            ->map(function ($account)
            {
                $balance = Payment::where('account_id', $account->id)
                    ->where('status', 2) // Approved
                    ->selectRaw('COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount ELSE -amount END), 0) as balance', [TransactionType::INCOME->value])
                    ->value('balance');

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'balance' => $balance,
                    'currency_code' => $account->currency->code ?? 'USD',
                ];
            });

        // Current month metrics
        $selectedMonth = Carbon::now()->month;

        $currentMonthIncome = Payment::where('transaction_type', TransactionType::INCOME)
            ->where('status', 2)
            ->whereMonth('date', $selectedMonth)
            ->whereYear('date', $selectedYear)
            ->sum('amount');

        $currentMonthExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->whereMonth('date', $selectedMonth)
            ->whereYear('date', $selectedYear)
            ->sum('amount');

        $currentMonthProfit = $currentMonthIncome - $currentMonthExpense;

        // Year to date metrics
        $ytdIncome = Payment::where('transaction_type', TransactionType::INCOME)
            ->where('status', 2)
            ->whereYear('date', $selectedYear)
            ->sum('amount');

        $ytdExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->whereYear('date', $selectedYear)
            ->sum('amount');

        $ytdProfit = $ytdIncome - $ytdExpense;

        // Monthly data for the selected year
        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++)
        {
            $date = Carbon::create($selectedYear, $month, 1);
            $monthlyIncome = Payment::where('transaction_type', TransactionType::INCOME)
                ->where('status', 2)
                ->whereMonth('date', $date->month)
                ->whereYear('date', $date->year)
                ->sum('amount');

            $monthlyExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
                ->where('status', 2)
                ->whereMonth('date', $date->month)
                ->whereYear('date', $date->year)
                ->sum('amount');

            $monthlyData[] = [
                'month' => $date->format('M Y'),
                'income' => $monthlyIncome,
                'expense' => $monthlyExpense,
                'profit' => $monthlyIncome - $monthlyExpense,
            ];
        }

        // Profit margin percentage
        $profitMargin = $ytdIncome > 0 ? ($ytdProfit / $ytdIncome) * 100 : 0;

        return view('finance-dashboard.index', compact(
            'accounts',
            'selectedYear',
            'availableYears',
            'currentMonthIncome',
            'currentMonthExpense',
            'currentMonthProfit',
            'ytdIncome',
            'ytdExpense',
            'ytdProfit',
            'monthlyData',
            'profitMargin',
        ));
    }
}
