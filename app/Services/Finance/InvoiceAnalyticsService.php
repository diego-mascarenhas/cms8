<?php

namespace App\Services\Finance;

use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates invoiced line items (by category and period) for financial projection reports.
 */
class InvoiceAnalyticsService
{
    /** @var list<int> Void, credit notes, error, draft — excluded from totals. */
    public const EXCLUDED_INVOICE_STATUSES = [3, 4, 6, 7, 9];

    public function __construct(
        private readonly PaymentReportingCurrencyService $reportingCurrencyService,
    ) {}

    /**
     * @return array{
     *     year: int,
     *     reporting_currency: string,
     *     available_years: list<int>,
     *     summary: array{
     *         income: float,
     *         expense: float,
     *         profit: float,
     *         margin_percent: float,
     *         prior_year_income: float,
     *         prior_year_expense: float,
     *         prior_year_profit: float,
     *     },
     *     monthly_trend: list<array{label: string, month: int, income: float, expense: float, profit: float}>,
     *     income_categories: list<array{id: int|null, name: string, total: float, share_percent: float}>,
     *     expense_categories: list<array{id: int|null, name: string, total: float, share_percent: float}>,
     *     scenario: array{avg_monthly_income: float, avg_monthly_expense: float, avg_monthly_profit: float},
     *     conversion: array{
     *         complete: bool,
     *         missing_pairs: list<string>,
     *         native_totals: array{income: array<string, float>, expense: array<string, float>},
     *     },
     * }
     */
    public function buildYearReport(int $teamId, int $year): array
    {
        $reportingCurrency = $this->reportingCurrencyService->reportingCurrencyForTeam($teamId);
        $bounds = $this->resolveYearBounds($teamId);
        $year = max($bounds['min'], min($year, $bounds['max']));
        $availableYears = range($bounds['max'], $bounds['min']);

        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->endOfDay();

        $currentPeriod = $this->aggregateMonthlyTotals($teamId, $from, $to, $reportingCurrency);
        $monthlyRows = $currentPeriod['rows'];
        $monthlyTrend = $this->formatMonthlyTrend($year, $monthlyRows);

        $income = (float) $monthlyRows->sum('income');
        $expense = (float) $monthlyRows->sum('expense');
        $profit = $income - $expense;

        $priorFrom = Carbon::create($year - 1, 1, 1)->startOfDay();
        $priorTo = Carbon::create($year - 1, 12, 31)->endOfDay();
        $priorPeriod = $this->aggregateMonthlyTotals($teamId, $priorFrom, $priorTo, $reportingCurrency);
        $priorMonthly = $priorPeriod['rows'];
        $priorIncome = (float) $priorMonthly->sum('income');
        $priorExpense = (float) $priorMonthly->sum('expense');

        $missingPairs = array_values(array_unique(array_merge(
            $currentPeriod['missing_pairs'],
            $priorPeriod['missing_pairs'],
        )));

        $monthsWithData = $monthlyRows->filter(fn (object $row) => ((float) $row->income) > 0 || ((float) $row->expense) > 0)->count();
        $divisor = max(1, $monthsWithData);

        return [
            'year' => $year,
            'reporting_currency' => $reportingCurrency,
            'available_years' => $availableYears,
            'summary' => [
                'income' => $income,
                'expense' => $expense,
                'profit' => $profit,
                'margin_percent' => $income > 0 ? ($profit / $income) * 100 : 0.0,
                'prior_year_income' => $priorIncome,
                'prior_year_expense' => $priorExpense,
                'prior_year_profit' => $priorIncome - $priorExpense,
            ],
            'monthly_trend' => $monthlyTrend,
            'income_categories' => $this->aggregateCategoryBreakdown($teamId, $from, $to, 'sell', $reportingCurrency),
            'expense_categories' => $this->aggregateCategoryBreakdown($teamId, $from, $to, 'buy', $reportingCurrency),
            'scenario' => [
                'avg_monthly_income' => $income / $divisor,
                'avg_monthly_expense' => $expense / $divisor,
                'avg_monthly_profit' => $profit / $divisor,
            ],
            'conversion' => [
                'complete' => $missingPairs === [],
                'missing_pairs' => $missingPairs,
                'native_totals' => $currentPeriod['native_totals'],
            ],
        ];
    }

    /**
     * @return array{
     *     year: int,
     *     multiplier: float,
     *     avg_monthly_profit: float,
     *     target_monthly_profit: float,
     *     monthly_gap: float,
     *     income_increase_percent: float|null,
     *     expense_decrease_percent: float|null,
     * }
     */
    public function buildGrowthScenario(int $teamId, int $year, float $multiplier): array
    {
        $multiplier = max(1.0, min(20.0, $multiplier));
        $report = $this->buildYearReport($teamId, $year);
        $scenario = $report['scenario'];
        $avgProfit = (float) $scenario['avg_monthly_profit'];
        $avgIncome = (float) $scenario['avg_monthly_income'];
        $avgExpense = (float) $scenario['avg_monthly_expense'];
        $targetProfit = $avgProfit * $multiplier;
        $gap = $targetProfit - $avgProfit;

        return [
            'year' => $report['year'],
            'multiplier' => $multiplier,
            'avg_monthly_profit' => $avgProfit,
            'target_monthly_profit' => $targetProfit,
            'monthly_gap' => $gap,
            'income_increase_percent' => ($gap > 0 && $avgIncome > 0) ? ($gap / $avgIncome) * 100 : null,
            'expense_decrease_percent' => ($gap > 0 && $avgExpense > 0) ? ($gap / $avgExpense) * 100 : null,
        ];
    }

    /**
     * Compact text summary for the assistant (truncated externally if needed).
     */
    public function formatYearReportForAssistant(array $report): string
    {
        $year = $report['year'];
        $currency = $report['reporting_currency'] ?? '';
        $summary = $report['summary'];
        $scenario = $report['scenario'];
        $suffix = $currency !== '' ? ' '.$currency : '';
        $lines = [
            "Financial projection (invoiced line items) for {$year}:",
            'Income: '.number_format($summary['income'], 2).$suffix,
            'Expenses: '.number_format($summary['expense'], 2).$suffix,
            'Net profit: '.number_format($summary['profit'], 2).$suffix.' (margin '.number_format($summary['margin_percent'], 1).'%)',
            'Prior year profit: '.number_format($summary['prior_year_profit'], 2).$suffix,
            'Avg monthly profit: '.number_format($scenario['avg_monthly_profit'], 2).$suffix,
        ];

        $lines[] = 'Top income categories:';
        foreach (array_slice($report['income_categories'], 0, 8) as $row)
        {
            $lines[] = '  - '.$row['name'].': '.number_format($row['total'], 2).' ('.number_format($row['share_percent'], 1).'%)';
        }

        $lines[] = 'Top expense categories:';
        foreach (array_slice($report['expense_categories'], 0, 8) as $row)
        {
            $lines[] = '  - '.$row['name'].': '.number_format($row['total'], 2).' ('.number_format($row['share_percent'], 1).'%)';
        }

        return implode("\n", $lines);
    }

    public function formatGrowthScenarioForAssistant(array $scenario): string
    {
        $lines = [
            "Growth scenario for {$scenario['year']} (×{$scenario['multiplier']} on avg monthly profit):",
            'Current avg monthly profit: '.number_format($scenario['avg_monthly_profit'], 2),
            'Target monthly profit: '.number_format($scenario['target_monthly_profit'], 2),
            'Monthly gap: '.number_format($scenario['monthly_gap'], 2),
        ];

        if ($scenario['income_increase_percent'] !== null)
        {
            $lines[] = 'Equivalent (~holding expenses): increase invoiced income by '
                .number_format($scenario['income_increase_percent'], 1).'% per month.';
        }

        if ($scenario['expense_decrease_percent'] !== null)
        {
            $lines[] = 'Equivalent (~holding income): reduce invoiced expenses by '
                .number_format($scenario['expense_decrease_percent'], 1).'% per month.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{min: int, max: int}
     */
    public function resolveYearBounds(int $teamId): array
    {
        $currentYear = (int) Carbon::now()->year;

        $years = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('date')
            ->whereNotIn('status', self::EXCLUDED_INVOICE_STATUSES)
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->year)
            ->unique()
            ->sort()
            ->values();

        if ($years->isEmpty())
        {
            return ['min' => $currentYear, 'max' => $currentYear];
        }

        return [
            'min' => (int) $years->first(),
            'max' => (int) min($years->last(), $currentYear),
        ];
    }

    /**
     * @return array{
     *     rows: Collection<int, object{month_num: int, income: float, expense: float}>,
     *     missing_pairs: list<string>,
     *     native_totals: array{income: array<string, float>, expense: array<string, float>},
     * }
     */
    private function aggregateMonthlyTotals(int $teamId, Carbon $from, Carbon $to, string $reportingCurrency): array
    {
        $monthly = [];
        $missingPairs = [];
        $nativeTotals = ['income' => [], 'expense' => []];

        for ($month = 1; $month <= 12; $month++)
        {
            $monthly[$month] = ['income' => 0.0, 'expense' => 0.0];
        }

        foreach ($this->loadItemsInRange($teamId, $from, $to) as $item)
        {
            $invoice = $item->invoice;

            if (! $invoice || blank($invoice->date))
            {
                continue;
            }

            $month = (int) Carbon::parse($invoice->date)->format('n');

            if ($month < 1 || $month > 12)
            {
                continue;
            }

            $converted = $this->convertedLineAmount($item, $reportingCurrency, $missingPairs, $nativeTotals);

            if ($converted === null)
            {
                continue;
            }

            if ($invoice->operation === 'sell')
            {
                $monthly[$month]['income'] += $converted;
            } elseif ($invoice->operation === 'buy')
            {
                $monthly[$month]['expense'] += $converted;
            }
        }

        $rows = collect($monthly)->map(function (array $totals, int $month): object
        {
            return (object) [
                'month_num' => $month,
                'income' => round($totals['income'], 2),
                'expense' => round($totals['expense'], 2),
            ];
        });

        return [
            'rows' => $rows,
            'missing_pairs' => array_values(array_unique($missingPairs)),
            'native_totals' => $nativeTotals,
        ];
    }

    /**
     * @param  Collection<int, object{month_num: int, income: float, expense: float}>  $monthlyRows
     * @return list<array{label: string, month: int, income: float, expense: float, profit: float}>
     */
    private function formatMonthlyTrend(int $year, Collection $monthlyRows): array
    {
        $trend = [];

        for ($month = 1; $month <= 12; $month++)
        {
            $row = $monthlyRows->get($month);
            $income = $row ? (float) $row->income : 0.0;
            $expense = $row ? (float) $row->expense : 0.0;
            $date = Carbon::create($year, $month, 1);

            $trend[] = [
                'label' => $date->translatedFormat('M Y'),
                'month' => $month,
                'income' => $income,
                'expense' => $expense,
                'profit' => $income - $expense,
            ];
        }

        return $trend;
    }

    /**
     * @return list<array{id: int|null, name: string, total: float, share_percent: float}>
     */
    private function aggregateCategoryBreakdown(int $teamId, Carbon $from, Carbon $to, string $operation, string $reportingCurrency): array
    {
        $totalsByCategory = [];

        foreach ($this->loadItemsInRange($teamId, $from, $to) as $item)
        {
            $invoice = $item->invoice;

            if (! $invoice || $invoice->operation !== $operation)
            {
                continue;
            }

            $key = $item->category_id !== null ? (int) $item->category_id : 0;
            $converted = $this->convertedLineAmount($item, $reportingCurrency);

            if ($converted === null)
            {
                continue;
            }

            $totalsByCategory[$key] = ($totalsByCategory[$key] ?? 0.0) + $converted;
        }

        if ($totalsByCategory === [])
        {
            return [];
        }

        arsort($totalsByCategory);
        $grandTotal = array_sum($totalsByCategory);

        if ($grandTotal <= 0)
        {
            return [];
        }

        $categoryIds = array_values(array_filter(array_keys($totalsByCategory), fn (int $id) => $id > 0));
        $categoryNames = $categoryIds !== []
            ? Category::withoutGlobalScopes()->whereIn('id', $categoryIds)->pluck('name', 'id')
            : collect();

        $breakdown = [];
        foreach ($totalsByCategory as $categoryKey => $total)
        {
            $categoryId = $categoryKey > 0 ? $categoryKey : null;
            $name = $categoryId !== null
                ? (string) ($categoryNames[$categoryId] ?? __('Uncategorized'))
                : __('Uncategorized');

            $breakdown[] = [
                'id' => $categoryId,
                'name' => $name,
                'total' => round($total, 2),
                'share_percent' => ($total / $grandTotal) * 100,
            ];
        }

        return $breakdown;
    }

    /**
     * @return Collection<int, InvoiceItem>
     */
    private function loadItemsInRange(int $teamId, Carbon $from, Carbon $to): Collection
    {
        return InvoiceItem::query()
            ->whereHas('invoice', function ($query) use ($teamId, $from, $to): void
            {
                $query->withoutGlobalScopes()
                    ->where('team_id', $teamId)
                    ->whereNotIn('status', self::EXCLUDED_INVOICE_STATUSES)
                    ->whereNotNull('date')
                    ->whereDate('date', '>=', $from->toDateString())
                    ->whereDate('date', '<=', $to->toDateString());
            })
            ->with(['invoice' => fn ($query) => $query->withoutGlobalScopes()->with('currency')])
            ->get();
    }

    private function lineNetAmount(InvoiceItem $item): float
    {
        return round(
            ((float) $item->quantity * (float) $item->unit_price) - (float) ($item->discount ?? 0),
            2,
        );
    }

    /**
     * @param  list<string>  $missingPairs
     * @param  array{income: array<string, float>, expense: array<string, float>}  $nativeTotals
     */
    private function convertedLineAmount(
        InvoiceItem $item,
        string $reportingCurrency,
        array &$missingPairs = [],
        array &$nativeTotals = ['income' => [], 'expense' => []],
    ): ?float {
        $invoice = $item->invoice;

        if (! $invoice)
        {
            return null;
        }

        $amount = $this->lineNetAmount($item);
        $fromCurrency = $invoice->currency_code;
        $reportingCurrency = strtoupper(trim($reportingCurrency));

        if ($fromCurrency === $reportingCurrency)
        {
            return $amount;
        }

        $conversionDate = Carbon::parse($invoice->date)->endOfMonth();
        $converted = ExchangeRate::convertOnOrBeforeDate($amount, $fromCurrency, $reportingCurrency, $conversionDate);

        if ($converted !== null)
        {
            return $converted;
        }

        $fallback = ExchangeRate::convert($amount, $fromCurrency, $reportingCurrency);

        if ($fallback !== null)
        {
            return $fallback;
        }

        $pair = "{$fromCurrency}->{$reportingCurrency}";
        $missingPairs[] = $pair;

        $side = $invoice->operation === 'sell' ? 'income' : 'expense';
        $nativeTotals[$side][$fromCurrency] = ($nativeTotals[$side][$fromCurrency] ?? 0.0) + $amount;

        return null;
    }
}
