<?php

namespace App\Http\Controllers;

use App\DataTables\ExpenseDataTable;
use App\Enums\TransactionType;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    public function index(ExpenseDataTable $dataTable)
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

        // Current month expenses
        $currentMonthExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        // Last month expenses
        $lastMonthExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->whereMonth('date', Carbon::now()->subMonth()->month)
            ->whereYear('date', Carbon::now()->subMonth()->year)
            ->sum('amount');

        // Calculate percentage change
        $percentageChange = $lastMonthExpense > 0
            ? (($currentMonthExpense - $lastMonthExpense) / $lastMonthExpense) * 100
            : 0;

        // Year to date expenses
        $ytdExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->whereYear('date', Carbon::now()->year)
            ->sum('amount');

        // Total approved expenses
        $totalExpense = Payment::where('transaction_type', TransactionType::EXPENSE)
            ->where('status', 2)
            ->sum('amount');

        return $dataTable->render('expense.index', compact(
            'accounts',
            'currentMonthExpense',
            'percentageChange',
            'ytdExpense',
            'totalExpense',
        ));
    }
}
