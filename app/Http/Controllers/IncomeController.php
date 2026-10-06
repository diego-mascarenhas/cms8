<?php

namespace App\Http\Controllers;

use App\DataTables\IncomeDataTable;
use App\Models\Payment;
use App\Services\Finance\PaymentReportingCurrencyService;
use App\Services\Finance\VatHaciendaCsvExportService;
use App\Services\Finance\VatReportingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomeController extends Controller
{
    public function __construct(
        private readonly PaymentReportingCurrencyService $paymentReportingCurrencyService,
        private readonly VatReportingService $vatReportingService,
        private readonly VatHaciendaCsvExportService $vatHaciendaCsvExportService,
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

        $teamId = (int) auth()->user()->currentTeam->id;
        $periodBooks = $this->vatHaciendaCsvExportService->bookSummary(
            $teamId,
            $periodRange['from'],
            $periodRange['to'],
            $reportingCurrency,
        );
        $periodIncome = $periodBooks['sales'];

        $previousPeriodBooks = $this->vatHaciendaCsvExportService->bookSummary(
            $teamId,
            $previousPeriodRange['from'],
            $previousPeriodRange['to'],
            $reportingCurrency,
        );
        $previousPeriodIncome = $previousPeriodBooks['sales'];

        $percentageChange = $previousPeriodIncome > 0
            ? (($periodIncome - $previousPeriodIncome) / $previousPeriodIncome) * 100
            : 0;

        $yearFrom = Carbon::create($vatSelection['year'], 1, 1)->startOfDay();
        $yearTo = $vatSelection['year'] === (int) now()->year
            ? now()->endOfDay()
            : Carbon::create($vatSelection['year'], 12, 31)->endOfDay();

        $yearBooks = $this->vatHaciendaCsvExportService->bookSummary(
            $teamId,
            $yearFrom,
            $yearTo,
            $reportingCurrency,
        );
        $yearIncome = $yearBooks['sales'];

        $previousYearFrom = $yearFrom->copy()->subYear();
        $previousYearTo = $yearTo->copy()->subYear();

        $previousYearBooks = $this->vatHaciendaCsvExportService->bookSummary(
            $teamId,
            $previousYearFrom,
            $previousYearTo,
            $reportingCurrency,
        );
        $previousYearIncome = $previousYearBooks['sales'];

        $yearPercentageChange = $previousYearIncome > 0
            ? (($yearIncome - $previousYearIncome) / $previousYearIncome) * 100
            : 0;

        $selectedVat = $periodBooks['output_vat'];

        $previousYearPeriodFrom = $periodRange['from']->copy()->subYear();
        $previousYearPeriodTo = $periodRange['to']->copy()->subYear();
        $previousYearVat = $this->vatHaciendaCsvExportService->bookSummary(
            $teamId,
            $previousYearPeriodFrom,
            $previousYearPeriodTo,
            $reportingCurrency,
        )['output_vat'];
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

    public function exportHacienda(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Payment::class);

        $vatSelection = $this->vatReportingService->resolveSelectedPeriod(
            year: $request->integer('vat_year') ?: null,
            period: $request->string('vat_period')->toString() ?: null,
        );

        return $this->vatHaciendaCsvExportService->download(
            operation: 'sell',
            from: $vatSelection['range']['from'],
            to: $vatSelection['range']['to'],
            periodLabel: $vatSelection['label'],
            documentScope: 'invoices',
        );
    }

    public function exportCreditNotes(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Payment::class);

        $vatSelection = $this->vatReportingService->resolveSelectedPeriod(
            year: $request->integer('vat_year') ?: null,
            period: $request->string('vat_period')->toString() ?: null,
        );

        return $this->vatHaciendaCsvExportService->download(
            operation: 'sell',
            from: $vatSelection['range']['from'],
            to: $vatSelection['range']['to'],
            periodLabel: $vatSelection['label'],
            documentScope: 'credit_notes',
        );
    }

    public function exportHaciendaPreviousQuarter(): BinaryFileResponse
    {
        $this->authorize('viewAny', Payment::class);

        return $this->vatHaciendaCsvExportService->downloadPreviousQuarterZip();
    }
}
