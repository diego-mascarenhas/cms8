<?php

namespace App\Http\Controllers;

use App\DataTables\IncomeDataTable;
use App\Enums\TransactionType;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    public function index(IncomeDataTable $dataTable)
    {
        // Get accounts with balances
        $accounts = PaymentAccount::with('currency')
            ->get()
            ->map(function ($account)
            {
                $balance = Payment::where('account_id', $account->id)
                    ->where('status', 2) // Approved
                    ->sum(DB::raw('CASE WHEN transaction_type = "income" THEN amount ELSE -amount END'));

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'balance' => $balance,
                    'currency_code' => $account->currency->code ?? 'USD',
                ];
            });

        // Current month income
        $currentMonthIncome = Payment::where('transaction_type', TransactionType::INCOME)
            ->where('status', 2)
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        // Last month income
        $lastMonthIncome = Payment::where('transaction_type', TransactionType::INCOME)
            ->where('status', 2)
            ->whereMonth('date', Carbon::now()->subMonth()->month)
            ->whereYear('date', Carbon::now()->subMonth()->year)
            ->sum('amount');

        // Calculate percentage change
        $percentageChange = $lastMonthIncome > 0
            ? (($currentMonthIncome - $lastMonthIncome) / $lastMonthIncome) * 100
            : 0;

        // Year to date income
        $ytdIncome = Payment::where('transaction_type', TransactionType::INCOME)
            ->where('status', 2)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        // Total approved income
        $totalIncome = Payment::where('transaction_type', TransactionType::INCOME)
            ->where('status', 2)
            ->sum('amount');

        return $dataTable->render('income.index', compact(
            'accounts',
            'currentMonthIncome',
            'percentageChange',
            'ytdIncome',
            'totalIncome',
        ));
    }
}
