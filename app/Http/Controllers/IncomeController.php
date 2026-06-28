<?php

namespace App\Http\Controllers;

use App\DataTables\IncomeDataTable;
use App\Enums\TransactionType;
use App\Models\Payment;
use App\Services\Finance\PaymentReportingCurrencyService;
use Carbon\Carbon;

class IncomeController extends Controller
{
    public function __construct(
        private readonly PaymentReportingCurrencyService $paymentReportingCurrencyService,
    ) {}

    public function index(IncomeDataTable $dataTable)
    {
        $this->authorize('viewAny', Payment::class);

        $accounts = $this->paymentReportingCurrencyService->accountBalancesForDisplay();
        $reportingCurrency = $this->paymentReportingCurrencyService->reportingCurrencyForCurrentTeam();

        $currentMonthIncome = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::INCOME,
            $reportingCurrency,
            fn ($query) => $query
                ->whereMonth('payments.date', Carbon::now()->month)
                ->whereYear('payments.date', Carbon::now()->year),
        );

        $lastMonthIncome = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::INCOME,
            $reportingCurrency,
            fn ($query) => $query
                ->whereMonth('payments.date', Carbon::now()->subMonth()->month)
                ->whereYear('payments.date', Carbon::now()->subMonth()->year),
        );

        $percentageChange = $lastMonthIncome > 0
            ? (($currentMonthIncome - $lastMonthIncome) / $lastMonthIncome) * 100
            : 0;

        $ytdIncome = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::INCOME,
            $reportingCurrency,
            fn ($query) => $query->whereYear('payments.date', Carbon::now()->year),
        );

        $totalIncome = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::INCOME,
            $reportingCurrency,
        );

        return $dataTable->render('income.index', compact(
            'accounts',
            'currentMonthIncome',
            'percentageChange',
            'ytdIncome',
            'totalIncome',
            'reportingCurrency',
        ));
    }
}
