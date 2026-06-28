<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\InvoiceAnalyticsService;
use App\Services\Finance\InvoicedLineItemsService;
use App\Services\Finance\PaymentReportingCurrencyService;
use App\Support\SqlDateExpressions;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinancialDashboardController extends Controller
{
    public function __construct(
        protected InvoiceAnalyticsService $invoiceAnalytics,
        protected InvoicedLineItemsService $invoicedLineItemsService,
        protected PaymentReportingCurrencyService $paymentReportingCurrencyService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::query();
        $reportingCurrency = $this->paymentReportingCurrencyService->reportingCurrencyForCurrentTeam();

        $currentYear = Carbon::now()->year;
        $requestedYear = (int) $request->query('year', $currentYear);
        $selectedYear = $requestedYear > 0 ? $requestedYear : $currentYear;

        $paymentYearSql = SqlDateExpressions::year('payments.date');

        $yearBounds = $payments->clone()->whereNotNull('payments.date')
            ->selectRaw("COALESCE(MIN({$paymentYearSql}), 0) as min_year, COALESCE(MAX({$paymentYearSql}), 0) as max_year")
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

        $accounts = $this->paymentReportingCurrencyService->accountBalancesForDisplay();
        $selectedMonth = Carbon::now()->month;
        $monthlyTotals = $this->paymentReportingCurrencyService->monthlyTotalsConverted($selectedYear, $reportingCurrency);

        $currentMonthIncome = (float) ($monthlyTotals[$selectedMonth]['income'] ?? 0);
        $currentMonthExpense = (float) ($monthlyTotals[$selectedMonth]['expense'] ?? 0);
        $currentMonthProfit = $currentMonthIncome - $currentMonthExpense;

        $ytdIncome = round(collect($monthlyTotals)->sum('income'), 2);
        $ytdExpense = round(collect($monthlyTotals)->sum('expense'), 2);
        $ytdProfit = $ytdIncome - $ytdExpense;

        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++)
        {
            $date = Carbon::create($selectedYear, $month, 1);
            $monthlyIncome = (float) ($monthlyTotals[$month]['income'] ?? 0);
            $monthlyExpense = (float) ($monthlyTotals[$month]['expense'] ?? 0);
            $monthlyData[] = [
                'month' => $date->format('M Y'),
                'income' => $monthlyIncome,
                'expense' => $monthlyExpense,
                'profit' => $monthlyIncome - $monthlyExpense,
            ];
        }

        $profitMargin = $ytdIncome > 0 ? ($ytdProfit / $ytdIncome) * 100 : 0;

        $incomeCategories = [];
        $expenseCategories = [];
        $invoiceReportingCurrency = $reportingCurrency;

        if (auth()->user()?->can('viewAny', Invoice::class))
        {
            $team = auth()->user()->currentTeam;

            if ($team !== null)
            {
                $invoiceReport = $this->invoiceAnalytics->buildYearReport((int) $team->id, $selectedYear);
                $incomeCategories = $invoiceReport['income_categories'];
                $expenseCategories = $invoiceReport['expense_categories'];
                $invoiceReportingCurrency = $invoiceReport['reporting_currency'];
            }
        }

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
            'reportingCurrency',
            'incomeCategories',
            'expenseCategories',
            'invoiceReportingCurrency',
            'selectedMonth',
        ));
    }

    public function projection(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $team = auth()->user()->currentTeam;
        abort_if($team === null, 403);

        $teamId = (int) $team->id;
        $bounds = $this->invoiceAnalytics->resolveYearBounds($teamId);
        $currentYear = (int) Carbon::now()->year;
        $requestedYear = (int) $request->query('year', $currentYear);
        $selectedYear = max($bounds['min'], min($requestedYear > 0 ? $requestedYear : $currentYear, $bounds['max']));

        $report = $this->invoiceAnalytics->buildYearReport($teamId, $selectedYear);
        $selectedMonth = Carbon::now()->month;

        return view('finance-dashboard.projection', [
            'report' => $report,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'availableYears' => $report['available_years'],
        ]);
    }

    public function invoicedLines(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $team = auth()->user()->currentTeam;
        abort_if($team === null, 403);

        $operation = (string) $request->query('operation', 'sell');
        if (! in_array($operation, ['sell', 'buy'], true))
        {
            abort(404);
        }

        $teamId = (int) $team->id;
        $period = $this->invoicedLineItemsService->resolvePeriodFilter($request);
        $items = $this->invoicedLineItemsService->queryItems(
            teamId: $teamId,
            from: $period['from'],
            to: $period['to'],
            operation: $operation,
        );

        $display = $this->invoicedLineItemsService->buildDisplayPayload(
            $items,
            $teamId,
            showDescription: true,
            showCategory: true,
        );

        $isIncome = $operation === 'sell';
        $periodLabel = $period['month'] > 0
            ? Carbon::create($period['year'], $period['month'], 1)->translatedFormat('F Y')
            : (string) $period['year'];

        return view('finance-dashboard.invoiced-lines', [
            'lines' => $display['lines'],
            'totalAmount' => $display['total'],
            'reportingCurrency' => $display['reporting_currency'],
            'conversionComplete' => $display['conversion_complete'],
            'availableYears' => $this->invoicedLineItemsService->availableYearsForTeam($teamId),
            'selectedYear' => $period['year'],
            'selectedMonth' => $period['month'],
            'amountTone' => $isIncome ? 'income' : 'expense',
            'backUrl' => $this->resolveFinanceBackUrl($request),
            'pageTitle' => $isIncome ? __('All invoiced income lines') : __('All invoiced expense lines'),
            'pageSubtitle' => $isIncome
                ? __('Invoiced revenue lines for :period', ['period' => $periodLabel])
                : __('Invoiced expense lines for :period', ['period' => $periodLabel]),
            'emptyMessage' => $isIncome
                ? __('No invoiced income in this period.')
                : __('No invoiced expenses in this period.'),
        ]);
    }

    private function resolveFinanceBackUrl(Request $request): string
    {
        $return = (string) $request->query('return', '');

        if ($return !== '' && str_starts_with($return, (string) config('app.url')))
        {
            return $return;
        }

        return route('finance-dashboard.index');
    }
}
