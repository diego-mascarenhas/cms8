<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Team;
use App\Models\TeamSnapshot;
use App\Services\TeamSnapshotService;
use App\Support\StripeInvoiceMetrics;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class FinanceDashboardSnapshotService
{
    public function __construct(
        private readonly PaymentReportingCurrencyService $paymentReportingCurrencyService,
        private readonly FinanceCfoBriefService $financeCfoBriefService,
        private readonly InvoiceAnalyticsService $invoiceAnalyticsService,
        private readonly TeamSnapshotService $teamSnapshotService,
    ) {}

    public function capture(Team $team, ?int $year = null): TeamSnapshot
    {
        $year = $year !== null && $year > 0 ? $year : (int) Carbon::now()->year;
        $payload = $this->buildPayload($team, $year);

        $snapshot = $this->teamSnapshotService->store(
            teamId: (int) $team->id,
            type: TeamSnapshot::TYPE_FINANCE_DASHBOARD,
            payload: $payload,
            key: 'year:'.$year,
        );

        $this->teamSnapshotService->prune(
            teamId: (int) $team->id,
            type: TeamSnapshot::TYPE_FINANCE_DASHBOARD,
            key: 'year:'.$year,
            keep: 48,
        );

        return $snapshot;
    }

    public function latest(Team $team, ?int $year = null): ?TeamSnapshot
    {
        $year = $year !== null && $year > 0 ? $year : (int) Carbon::now()->year;

        return $this->teamSnapshotService->latest(
            (int) $team->id,
            TeamSnapshot::TYPE_FINANCE_DASHBOARD,
            'year:'.$year,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Team $team, int $year): array
    {
        $cash = $this->buildCashPosition($team, $year);
        $projection = $this->financeCfoBriefService->projection($team, $year);
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $categories = $this->invoiceAnalyticsService->buildCategoryBreakdownForRange(
            (int) $team->id,
            $monthStart,
            $monthEnd,
        );

        return [
            'year' => $year,
            'captured_at' => now()->toIso8601String(),
            'cash_position' => $cash,
            'flow_projection' => [
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
            ],
            'category_charts' => [
                'success' => true,
                'vat_year' => $year,
                'vat_period' => 'm:'.Carbon::now()->month,
                'period_label' => Carbon::now()->translatedFormat('F Y'),
                'reporting_currency' => (string) ($categories['reporting_currency'] ?? ''),
                'income_categories' => $categories['income_categories'] ?? [],
                'expense_categories' => $categories['expense_categories'] ?? [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCashPosition(Team $team, ?int $year = null): array
    {
        $year = $year !== null && $year > 0 ? $year : (int) Carbon::now()->year;
        $teamId = (int) $team->id;
        $reportingCurrency = $this->paymentReportingCurrencyService->reportingCurrencyForTeam($team);
        $accounts = $this->paymentReportingCurrencyService->accountBalancesWithBank($team);
        $liquidByCurrency = [];
        $bookByCurrency = [];
        $bankByCurrency = [];
        $accountsWithBank = 0;
        $accountsWithBookOnly = 0;

        foreach ($accounts as $account)
        {
            $code = strtoupper((string) ($account['currency_code'] ?? 'EUR'));
            $liquidByCurrency[$code] = ($liquidByCurrency[$code] ?? 0.0) + (float) ($account['balance'] ?? 0);
            $bookByCurrency[$code] = ($bookByCurrency[$code] ?? 0.0) + (float) ($account['book_balance'] ?? 0);

            if ($account['bank_balance'] !== null)
            {
                $bankByCurrency[$code] = ($bankByCurrency[$code] ?? 0.0) + (float) $account['bank_balance'];
                $accountsWithBank++;
            } else
            {
                $accountsWithBookOnly++;
            }
        }

        $liquidAssets = $this->convertTotals($liquidByCurrency, $reportingCurrency);
        $bookLiquidAssets = $this->convertTotals($bookByCurrency, $reportingCurrency);
        $bankLiquidAssets = $accountsWithBank > 0
            ? $this->convertTotals($bankByCurrency, $reportingCurrency)
            : null;
        $liquidSource = $accountsWithBank === 0
            ? 'book'
            : ($accountsWithBookOnly === 0 ? 'bank' : 'mixed');
        $receivables = $this->outstandingBalance($teamId, 'sell', $reportingCurrency);
        $payables = $this->outstandingBalance($teamId, 'buy', $reportingCurrency);
        $netPosition = round($liquidAssets + $receivables['amount'] - $payables['amount'], 2);
        $projection = $this->financeCfoBriefService->projection($team, $year);
        $avgMonthlyExpense = (float) ($projection['avg_monthly_expense'] ?? 0);
        $runwayMonths = $avgMonthlyExpense > 0
            ? round($liquidAssets / $avgMonthlyExpense, 1)
            : null;

        return [
            'success' => true,
            'reporting_currency' => $reportingCurrency,
            'liquid_assets' => round($liquidAssets, 2),
            'book_liquid_assets' => round($bookLiquidAssets, 2),
            'bank_liquid_assets' => $bankLiquidAssets !== null ? round($bankLiquidAssets, 2) : null,
            'liquid_source' => $liquidSource,
            'receivables' => $receivables,
            'payables' => $payables,
            'net_position' => $netPosition,
            'avg_monthly_expense' => round($avgMonthlyExpense, 2),
            'runway_months' => $runwayMonths,
            'accounts' => $accounts->values()->all(),
        ];
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
