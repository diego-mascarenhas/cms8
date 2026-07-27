<?php

namespace App\Services\Finance;

use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Support\StripeInvoiceMetrics;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class InvoiceSummaryService
{
    /** @var list<int> Void, uncollectible, draft — excluded from payment summaries. */
    public const EXCLUDED_STATUSES = [3, 7, 9];

    /** @var list<int> */
    public const CREDIT_NOTE_STATUSES = [4, 6];

    /** @var list<int> Bonificada — treated as zero balance, not pending collection. */
    public const BONIFIED_STATUSES = [5, 6];

    /** @var list<int> Not treated as pending collection (only open/issued sales with balance). */
    public const UNPAID_EXCLUDED_STATUSES = [3, 4, 5, 6, 7, 9];

    /** @var list<int> */
    public const COLLECTED_EXCLUDED_STATUSES = [3, 4, 6, 7, 9];

    /** @var list<string> */
    public const SUMMARY_FILTERS = ['unpaid', 'credit_notes', 'collected', 'overdue'];

    /** @var list<string> Dashboard KPI cards (not invoice list filters). */
    public const DASHBOARD_CARDS = ['unpaid', 'overdue', 'expenses', 'profit'];

    public const DEFAULT_LIST_FILTER = 'all';

    public const ROLLING_SUMMARY_DAYS = 30;

    /** @var list<string> Filters whose card totals use a rolling date window (list may still show all). */
    public const ROLLING_SUMMARY_FILTERS = ['credit_notes', 'collected'];

    public function __construct(
        private readonly InvoiceAnalyticsService $invoiceAnalytics,
    ) {}

    /**
     * @return array{
     *     unpaid: array{count: int, amount_label: string, totals_by_currency: array<string, float>},
     *     credit_notes: array{count: int, amount_label: string, totals_by_currency: array<string, float>},
     *     collected: array{count: int, amount_label: string, totals_by_currency: array<string, float>},
     *     overdue: array{count: int, amount_label: string, totals_by_currency: array<string, float>},
     * }
     */
    public function buildIndexStats(int $teamId): array
    {
        return [
            'unpaid' => $this->buildUnpaidMetric($teamId),
            'credit_notes' => $this->buildCreditNotesMetric($teamId),
            'collected' => $this->buildCollectedMetric($teamId),
            'overdue' => $this->buildOverdueMetric($teamId),
        ];
    }

    /**
     * Collection urgency + year-to-date P&L for the home dashboard.
     *
     * @return array{
     *     unpaid: array{count: int, amount_label: string, totals_by_currency: array<string, float>},
     *     overdue: array{count: int, amount_label: string, totals_by_currency: array<string, float>},
     *     expenses: array{count: int, amount_label: string, totals_by_currency: array<string, float>, url: string},
     *     profit: array{count: int|null, amount_label: string, totals_by_currency: array<string, float>, url: string, meta_label: string},
     * }
     */
    public function buildDashboardStats(int $teamId): array
    {
        $year = (int) Carbon::now()->year;
        $report = $this->invoiceAnalytics->buildYearReport($teamId, $year);
        $currency = strtoupper((string) $report['reporting_currency']);
        $expense = round((float) $report['summary']['expense'], 2);
        $profit = round((float) $report['summary']['profit'], 2);
        $resolvedYear = (int) $report['year'];
        $projectionUrl = route('finance-dashboard.projection', ['year' => $resolvedYear]);

        $expenseInvoiceCount = (int) Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('operation', 'buy')
            ->whereNotIn('status', InvoiceAnalyticsService::EXCLUDED_INVOICE_STATUSES)
            ->whereYear('date', $resolvedYear)
            ->count();

        return [
            'unpaid' => $this->buildUnpaidMetric($teamId),
            'overdue' => $this->buildOverdueMetric($teamId),
            'expenses' => [
                'count' => $expenseInvoiceCount,
                'amount_label' => Helpers::formatMoney($expense, $currency),
                'totals_by_currency' => [$currency => $expense],
                'url' => $projectionUrl,
            ],
            'profit' => [
                'count' => null,
                'amount_label' => Helpers::formatMoney($profit, $currency),
                'totals_by_currency' => [$currency => $profit],
                'url' => $projectionUrl,
                'meta_label' => __('app.invoice_summary_profit_margin', [
                    'percent' => number_format((float) $report['summary']['margin_percent'], 1),
                ]),
            ],
        ];
    }

    /**
     * @return array{count: int, amount_label: string, totals_by_currency: array<string, float>}
     */
    private function buildUnpaidMetric(int $teamId): array
    {
        $query = $this->baseQuery($teamId);
        $this->applySummaryFilter($query, 'unpaid');

        return $this->buildMetric($query, 'balance');
    }

    /**
     * @return array{count: int, amount_label: string, totals_by_currency: array<string, float>}
     */
    private function buildCreditNotesMetric(int $teamId): array
    {
        $query = $this->baseQuery($teamId);
        $this->applySummaryFilter($query, 'credit_notes');
        $this->applyRollingDateFilter($query);

        return $this->buildMetric($query, 'total_amount');
    }

    /**
     * @return array{count: int, amount_label: string, totals_by_currency: array<string, float>}
     */
    private function buildCollectedMetric(int $teamId): array
    {
        $query = $this->baseQuery($teamId);
        $this->applySummaryFilter($query, 'collected');
        $this->applyRollingDateFilter($query);

        return $this->buildMetric($query, 'total_amount');
    }

    /**
     * @return array{count: int, amount_label: string, totals_by_currency: array<string, float>}
     */
    private function buildOverdueMetric(int $teamId): array
    {
        $query = $this->baseQuery($teamId);
        $this->applySummaryFilter($query, 'overdue');

        return $this->buildMetric($query, 'balance');
    }

    public function resolveListFilter(?string $filter): string
    {
        $filter = $filter ?? self::DEFAULT_LIST_FILTER;

        $applicableFilters = array_merge(
            self::SUMMARY_FILTERS,
            [self::DEFAULT_LIST_FILTER, 'excluding_collected'],
        );

        if (! in_array($filter, $applicableFilters, true))
        {
            return self::DEFAULT_LIST_FILTER;
        }

        return $filter;
    }

    public function applySummaryFilter(Builder $query, string $filter): Builder
    {
        if ($filter === 'all')
        {
            return $query;
        }

        $this->constrainToSales($query);

        return match ($filter)
        {
            'unpaid', 'excluding_collected' => $query
                ->whereNotIn('invoices.status', self::UNPAID_EXCLUDED_STATUSES)
                ->where('invoices.balance', '>', 0),
            'credit_notes' => $query->where(function (Builder $creditNotes): void
            {
                $creditNotes->whereIn('invoices.status', self::CREDIT_NOTE_STATUSES)
                    ->orWhere('invoices.type_id', 2)
                    ->orWhere('invoices.source_reference_id', 'like', 'cn_%');
            }),
            'collected' => $this->applyRollingDateFilter(
                $query
                    ->whereNotIn('invoices.status', self::COLLECTED_EXCLUDED_STATUSES)
                    ->where(function (Builder $inner): void
                    {
                        $inner->whereIn('invoices.status', self::BONIFIED_STATUSES)
                            ->orWhere('invoices.balance', '<=', 0);
                    }),
            ),
            'overdue' => $query
                ->whereNotIn('invoices.status', self::UNPAID_EXCLUDED_STATUSES)
                ->where('invoices.balance', '>', 0)
                ->whereNotNull('invoices.due_date')
                ->whereDate('invoices.due_date', '<', Carbon::now()->startOfDay()->toDateString()),
            default => $query,
        };
    }

    private function baseQuery(int $teamId): Builder
    {
        return $this->constrainToSales(
            Invoice::withoutGlobalScopes()->where('team_id', $teamId),
        );
    }

    private function constrainToSales(Builder $query): Builder
    {
        return $query->where('invoices.operation', 'sell');
    }

    private function applyRollingDateFilter(Builder $query): Builder
    {
        $fromDate = Carbon::now()->subDays(self::ROLLING_SUMMARY_DAYS)->startOfDay()->toDateString();

        return $query->whereDate('invoices.date', '>=', $fromDate);
    }

    /**
     * @return array{count: int, amount_label: string, totals_by_currency: array<string, float>}
     */
    private function buildMetric(Builder $query, string $amountColumn): array
    {
        $count = (int) (clone $query)->count();

        if (! Schema::hasColumn('invoices', 'currency_id'))
        {
            $total = round((float) (clone $query)->sum($amountColumn), 2);
            $totalsByCurrency = ['EUR' => $total];

            return [
                'count' => $count,
                'amount_label' => $this->formatTotalsByCurrency($totalsByCurrency),
                'totals_by_currency' => $totalsByCurrency,
            ];
        }

        $defaultCurrencyCode = strtoupper((string) config('verifactu.default_currency', 'EUR'));

        $rows = (clone $query)
            ->leftJoin('currencies', 'invoices.currency_id', '=', 'currencies.id')
            ->select("invoices.{$amountColumn} as amount")
            ->addSelect('currencies.code as resolved_currency_code')
            ->toBase()
            ->get();

        $totalsByCurrency = [];
        foreach ($rows as $row)
        {
            $currency = strtoupper((string) ($row->resolved_currency_code ?: $defaultCurrencyCode));
            $totalsByCurrency[$currency] = round(
                ($totalsByCurrency[$currency] ?? 0) + (float) $row->amount,
                2,
            );
        }

        return [
            'count' => $count,
            'amount_label' => $this->formatTotalsByCurrency($totalsByCurrency),
            'totals_by_currency' => $totalsByCurrency,
        ];
    }

    /**
     * @param  array<string, float>  $totalsByCurrency
     */
    private function formatTotalsByCurrency(array $totalsByCurrency): string
    {
        if ($totalsByCurrency === [])
        {
            return Helpers::formatMoney(0, 'EUR');
        }

        $eurTotal = StripeInvoiceMetrics::sumAmountsConvertedToCurrency($totalsByCurrency, 'EUR');
        if ($eurTotal !== null)
        {
            return Helpers::formatMoney(round($eurTotal, 2), 'EUR');
        }

        ksort($totalsByCurrency);

        $parts = [];
        foreach ($totalsByCurrency as $currency => $amount)
        {
            $parts[] = Helpers::formatMoney($amount, $currency);
        }

        return implode(' · ', $parts);
    }
}
