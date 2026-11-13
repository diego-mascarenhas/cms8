<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\CfoBriefLauncher;
use App\Services\Finance\FinanceCfoBriefService;
use App\Services\Finance\InvoiceSummaryService;
use App\Services\Finance\PaymentReportingCurrencyService;
use App\Support\StripeInvoiceMetrics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FinanceAnalyticsController extends Controller
{
    public function __construct(
        private readonly PaymentReportingCurrencyService $paymentReportingCurrencyService,
        private readonly FinanceCfoBriefService $financeCfoBriefService,
        private readonly CfoBriefLauncher $cfoBriefLauncher,
    ) {}

    public function cashPosition(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $teamId = (int) $team->id;
        $reportingCurrency = $this->paymentReportingCurrencyService->reportingCurrencyForTeam($team);
        $accounts = $this->paymentReportingCurrencyService->accountBalancesForDisplay();
        $liquidByCurrency = [];

        foreach ($accounts as $account)
        {
            $code = strtoupper((string) ($account['currency_code'] ?? 'EUR'));
            $liquidByCurrency[$code] = ($liquidByCurrency[$code] ?? 0.0) + (float) ($account['balance'] ?? 0);
        }

        $liquidAssets = $this->convertTotals($liquidByCurrency, $reportingCurrency);
        $receivables = $this->outstandingBalance($teamId, 'sell', $reportingCurrency);
        $payables = $this->outstandingBalance($teamId, 'buy', $reportingCurrency);
        $netPosition = round($liquidAssets + $receivables['amount'] - $payables['amount'], 2);

        $projection = $this->financeCfoBriefService->projection($team, (int) Carbon::now()->year);
        $avgMonthlyExpense = (float) ($projection['avg_monthly_expense'] ?? 0);
        $runwayMonths = $avgMonthlyExpense > 0
            ? round($liquidAssets / $avgMonthlyExpense, 1)
            : null;

        return response()->json([
            'success' => true,
            'reporting_currency' => $reportingCurrency,
            'liquid_assets' => round($liquidAssets, 2),
            'receivables' => $receivables,
            'payables' => $payables,
            'net_position' => $netPosition,
            'avg_monthly_expense' => round($avgMonthlyExpense, 2),
            'runway_months' => $runwayMonths,
            'accounts' => $accounts->values()->all(),
        ]);
    }

    public function flowProjection(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $year = (int) $request->query('year', Carbon::now()->year);
        $year = $year > 0 ? $year : (int) Carbon::now()->year;
        $projection = $this->financeCfoBriefService->projection($team, $year);

        return response()->json([
            'success' => true,
            'year' => $year,
            'reporting_currency' => (string) ($projection['currency'] ?? ''),
            'avg_monthly_income' => (float) ($projection['avg_monthly_income'] ?? 0),
            'avg_monthly_expense' => (float) ($projection['avg_monthly_expense'] ?? 0),
            'avg_monthly_profit' => (float) ($projection['avg_monthly_profit'] ?? 0),
            'chart_income' => (float) ($projection['chart_income'] ?? 0),
            'chart_expense' => (float) ($projection['chart_expense'] ?? 0),
            'year_profit' => (float) ($projection['year_profit'] ?? 0),
            'months_with_data' => (int) ($projection['months_with_data'] ?? 0),
            'contracted_income' => (float) ($projection['contracted_income'] ?? 0),
            'contracted_expense' => (float) ($projection['contracted_expense'] ?? 0),
            'points' => $projection['points'] ?? [],
        ]);
    }

    public function storeCfoBrief(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $year = (int) $request->input('year', Carbon::now()->year);
        $year = $year > 0 ? $year : (int) Carbon::now()->year;
        $started = $this->financeCfoBriefService->begin($team, $year);

        if ($started)
        {
            $this->cfoBriefLauncher->start($team->id, $year);
        }

        return response()->json([
            'success' => true,
            'started' => $started,
            'year' => $year,
            'run' => $this->financeCfoBriefService->runStatus($team, $year),
        ]);
    }

    public function cfoStatus(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team === null)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        $year = (int) $request->query('year', Carbon::now()->year);
        $year = $year > 0 ? $year : (int) Carbon::now()->year;

        return response()->json([
            'success' => true,
            'year' => $year,
            'run' => $this->financeCfoBriefService->runStatus($team, $year),
            'analysis' => $this->financeCfoBriefService->storedAnalysis($team, $year),
        ]);
    }

    /**
     * @return array{amount: float, count: int}
     */
    private function outstandingBalance(int $teamId, string $operation, string $reportingCurrency): array
    {
        $query = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNull('invoices.deleted_at')
            ->where('invoices.operation', $operation)
            ->whereNotIn('invoices.status', InvoiceSummaryService::UNPAID_EXCLUDED_STATUSES)
            ->where('invoices.balance', '>', 0);

        $count = (int) (clone $query)->count();

        if (! Schema::hasColumn('invoices', 'currency_id'))
        {
            return [
                'amount' => round((float) (clone $query)->sum('balance'), 2),
                'count' => $count,
            ];
        }

        $defaultCurrencyCode = strtoupper((string) config('verifactu.default_currency', 'EUR'));
        $rows = (clone $query)
            ->leftJoin('currencies', 'invoices.currency_id', '=', 'currencies.id')
            ->select('invoices.balance as amount')
            ->addSelect('currencies.code as resolved_currency_code')
            ->toBase()
            ->get();

        $totalsByCurrency = [];

        foreach ($rows as $row)
        {
            $currency = strtoupper((string) ($row->resolved_currency_code ?: $defaultCurrencyCode));
            $totalsByCurrency[$currency] = ($totalsByCurrency[$currency] ?? 0.0) + (float) $row->amount;
        }

        return [
            'amount' => round($this->convertTotals($totalsByCurrency, $reportingCurrency), 2),
            'count' => $count,
        ];
    }

    /**
     * @param  array<string, float>  $totalsByCurrency
     */
    private function convertTotals(array $totalsByCurrency, string $reportingCurrency): float
    {
        if ($totalsByCurrency === [])
        {
            return 0.0;
        }

        $converted = StripeInvoiceMetrics::sumAmountsConvertedToCurrency($totalsByCurrency, $reportingCurrency);

        if ($converted !== null)
        {
            return (float) $converted;
        }

        $sameCurrency = $totalsByCurrency[strtoupper($reportingCurrency)] ?? null;

        return $sameCurrency !== null ? (float) $sameCurrency : (float) array_sum($totalsByCurrency);
    }
}
