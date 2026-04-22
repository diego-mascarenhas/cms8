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
        // Full financial history for this team (do not apply the July 2024 cutoff used elsewhere).
        $payments = Payment::withoutGlobalScope('fromJuly2024');

        $currentYear = Carbon::now()->year;
        $requestedYear = (int) $request->query('year', $currentYear);
        $selectedYear = $requestedYear > 0 ? $requestedYear : $currentYear;

        $yearBounds = $payments->clone()->whereNotNull('date')
            ->selectRaw('COALESCE(MIN(EXTRACT(YEAR FROM "date"))::int, 0) as min_year, COALESCE(MAX(EXTRACT(YEAR FROM "date"))::int, 0) as max_year')
            ->first();

        $minYear = (int) ($yearBounds->min_year ?? 0);
        $maxYear = (int) ($yearBounds->max_year ?? 0);

        if ($minYear === 0 || $maxYear === 0)
        {
            $minYear = $currentYear;
            $maxYear = $currentYear;
        }

        $minYear = min($minYear, $currentYear);
        $maxYear = max($maxYear, $currentYear);
        $selectedYear = max($minYear, min($selectedYear, $maxYear));
        $availableYears = range($maxYear, $minYear);

        $incomeType = TransactionType::INCOME->value;
        $expenseType = TransactionType::EXPENSE->value;

        // Account balances: sum payments by account (payment row status is not used here).
        // Which rows load as PaymentAccount is controlled by the model (active account status = 1).
        $balanceByAccountId = $payments->clone()
            ->whereNotNull('account_id')
            ->groupBy('account_id')
            ->selectRaw(
                'account_id, COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount ELSE -amount END), 0) as balance',
                [$incomeType]
            )
            ->pluck('balance', 'account_id');

        $accountIds = $balanceByAccountId->keys()->values();

        $accountsCollection = $accountIds->isNotEmpty()
            ? PaymentAccount::with('currency')->whereIn('id', $accountIds)->orderBy('name')->get()
            : collect();

        $accounts = $accountsCollection->map(function ($account) use ($balanceByAccountId)
        {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'balance' => (float) ($balanceByAccountId[$account->id] ?? 0),
                'currency_code' => $account->currency->code ?? 'USD',
            ];
        });

        $selectedMonth = Carbon::now()->month;

        $monthRows = $payments->clone()
            ->where('status', 2)
            ->whereYear('date', $selectedYear)
            ->groupByRaw('EXTRACT(MONTH FROM "date")::int')
            ->selectRaw(
                'EXTRACT(MONTH FROM "date")::int as month_num, '.
                'COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount ELSE 0 END), 0) as monthly_income, '.
                'COALESCE(SUM(CASE WHEN transaction_type = ? THEN amount ELSE 0 END), 0) as monthly_expense',
                [$incomeType, $expenseType]
            )
            ->get()
            ->keyBy(fn ($row) => (int) $row->month_num);

        $currentMonthRow = $monthRows->get($selectedMonth);
        $currentMonthIncome = $currentMonthRow ? (float) $currentMonthRow->monthly_income : 0.0;
        $currentMonthExpense = $currentMonthRow ? (float) $currentMonthRow->monthly_expense : 0.0;
        $currentMonthProfit = $currentMonthIncome - $currentMonthExpense;

        $ytdIncome = (float) $monthRows->sum('monthly_income');
        $ytdExpense = (float) $monthRows->sum('monthly_expense');
        $ytdProfit = $ytdIncome - $ytdExpense;

        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++)
        {
            $date = Carbon::create($selectedYear, $month, 1);
            $row = $monthRows->get($month);
            $monthlyIncome = $row ? (float) $row->monthly_income : 0.0;
            $monthlyExpense = $row ? (float) $row->monthly_expense : 0.0;
            $monthlyData[] = [
                'month' => $date->format('M Y'),
                'income' => $monthlyIncome,
                'expense' => $monthlyExpense,
                'profit' => $monthlyIncome - $monthlyExpense,
            ];
        }

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
