<?php

namespace App\Http\Controllers;

use App\DataTables\IncomeDataTable;
use App\Enums\TransactionType;
use App\Models\Payment;
use App\Services\Finance\PaymentReportingCurrencyService;
use App\Services\Finance\VatReportingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function __construct(
        private readonly PaymentReportingCurrencyService $paymentReportingCurrencyService,
        private readonly VatReportingService $vatReportingService,
    ) {}

    public function index(Request $request, IncomeDataTable $dataTable)
    {
        $this->authorize('viewAny', Payment::class);

        $accounts = $this->paymentReportingCurrencyService->accountBalancesForDisplay();
        $reportingCurrency = $this->paymentReportingCurrencyService->reportingCurrencyForCurrentTeam();

        $vatSelection = $this->vatReportingService->resolveSelectedPeriod(
            year: $request->integer('vat_year') ?: null,
            period: $request->string('vat_period')->toString() ?: null,
        );

        $periodRange = $vatSelection['range'];
        $previousPeriodRange = $this->vatReportingService->previousComparableRange($vatSelection);

        $periodIncome = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::INCOME,
            $reportingCurrency,
            fn ($query) => $query
                ->whereDate('payments.date', '>=', $periodRange['from']->toDateString())
                ->whereDate('payments.date', '<=', $periodRange['to']->toDateString()),
        );

        $previousPeriodIncome = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::INCOME,
            $reportingCurrency,
            fn ($query) => $query
                ->whereDate('payments.date', '>=', $previousPeriodRange['from']->toDateString())
                ->whereDate('payments.date', '<=', $previousPeriodRange['to']->toDateString()),
        );

        $percentageChange = $previousPeriodIncome > 0
            ? (($periodIncome - $previousPeriodIncome) / $previousPeriodIncome) * 100
            : 0;

        $yearFrom = Carbon::create($vatSelection['year'], 1, 1)->startOfDay();
        $yearTo = $vatSelection['year'] === (int) now()->year
            ? now()->endOfDay()
            : Carbon::create($vatSelection['year'], 12, 31)->endOfDay();

        $yearIncome = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::INCOME,
            $reportingCurrency,
            fn ($query) => $query
                ->whereDate('payments.date', '>=', $yearFrom->toDateString())
                ->whereDate('payments.date', '<=', $yearTo->toDateString()),
        );

        $previousYearFrom = $yearFrom->copy()->subYear();
        $previousYearTo = $yearTo->copy()->subYear();

        $previousYearIncome = $this->paymentReportingCurrencyService->sumApprovedPaymentsConverted(
            TransactionType::INCOME,
            $reportingCurrency,
            fn ($query) => $query
                ->whereDate('payments.date', '>=', $previousYearFrom->toDateString())
                ->whereDate('payments.date', '<=', $previousYearTo->toDateString()),
        );

        $yearPercentageChange = $previousYearIncome > 0
            ? (($yearIncome - $previousYearIncome) / $previousYearIncome) * 100
            : 0;

        $selectedVat = $this->vatReportingService->sumIncomeVat(
            $periodRange['from'],
            $periodRange['to'],
            $reportingCurrency,
        );

        $previousYearPeriodFrom = $periodRange['from']->copy()->subYear();
        $previousYearPeriodTo = $periodRange['to']->copy()->subYear();
        $previousYearVat = $this->vatReportingService->sumIncomeVat(
            $previousYearPeriodFrom,
            $previousYearPeriodTo,
            $reportingCurrency,
        );
        $vatPercentageChange = $previousYearVat > 0
            ? (($selectedVat - $previousYearVat) / $previousYearVat) * 100
            : 0;

        $selectedVatLabel = $vatSelection['label'];
        $periodLabel = $vatSelection['label'];
        $vatYears = $vatSelection['years'];
        $vatYear = $vatSelection['year'];
        $vatPeriod = $vatSelection['period'];
        $vatMode = $vatSelection['mode'];

        return $dataTable->render('income.index', compact(
            'accounts',
            'periodIncome',
            'previousPeriodIncome',
            'percentageChange',
            'yearIncome',
            'previousYearIncome',
            'yearPercentageChange',
            'reportingCurrency',
            'selectedVat',
            'previousYearVat',
            'vatPercentageChange',
            'selectedVatLabel',
            'periodLabel',
            'vatYears',
            'vatYear',
            'vatPeriod',
            'vatMode',
        ));
    }
}
