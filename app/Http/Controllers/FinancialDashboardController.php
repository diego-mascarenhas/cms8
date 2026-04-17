<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Carbon\Carbon;

class FinancialDashboardController extends Controller
{
    public function index()
    {
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
        $currentMonthIncome = Payment::where('transaction_type', TransactionType::INCOME)
            ->where('status', 2)
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        $currentMonthExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        $currentMonthProfit = $currentMonthIncome - $currentMonthExpense;

        // Year to date metrics
        $ytdIncome = Payment::where('transaction_type', TransactionType::INCOME)
            ->where('status', 2)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        $ytdExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        $ytdProfit = $ytdIncome - $ytdExpense;

        // Monthly data for chart (last 12 months)
        $monthlyData = [];
        for ($i = 11; $i >= 0; $i--)
        {
            $date = Carbon::now()->subMonths($i);
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
