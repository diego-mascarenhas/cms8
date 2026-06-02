<?php

namespace App\Services\Finance;

use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates invoiced line items (by category and period) for financial projection reports.
 */
class InvoiceAnalyticsService
{
    /** @var list<int> Void, credit notes, error, draft — excluded from totals. */
    public const EXCLUDED_INVOICE_STATUSES = [3, 4, 6, 7, 9];

    private const LINE_AMOUNT_SQL = '(invoice_items.quantity * invoice_items.unit_price - COALESCE(invoice_items.discount, 0))';

    /**
     * @return array{
     *     year: int,
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
     * }
     */
    public function buildYearReport(int $teamId, int $year): array
    {
        $bounds = $this->resolveYearBounds($teamId);
        $year = max($bounds['min'], min($year, $bounds['max']));
        $availableYears = range($bounds['max'], $bounds['min']);

        $from = Carbon::create($year, 1, 1)->startOfDay();
        $to = Carbon::create($year, 12, 31)->endOfDay();

        $monthlyRows = $this->aggregateMonthlyTotals($teamId, $from, $to);
        $monthlyTrend = $this->formatMonthlyTrend($year, $monthlyRows);

        $income = (float) $monthlyRows->sum('income');
        $expense = (float) $monthlyRows->sum('expense');
        $profit = $income - $expense;

        $priorFrom = Carbon::create($year - 1, 1, 1)->startOfDay();
        $priorTo = Carbon::create($year - 1, 12, 31)->endOfDay();
        $priorMonthly = $this->aggregateMonthlyTotals($teamId, $priorFrom, $priorTo);
        $priorIncome = (float) $priorMonthly->sum('income');
        $priorExpense = (float) $priorMonthly->sum('expense');

        $monthsWithData = $monthlyRows->filter(fn (object $row) => ((float) $row->income) > 0 || ((float) $row->expense) > 0)->count();
        $divisor = max(1, $monthsWithData);

        return [
            'year' => $year,
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
            'income_categories' => $this->aggregateCategoryBreakdown($teamId, $from, $to, 'sell'),
            'expense_categories' => $this->aggregateCategoryBreakdown($teamId, $from, $to, 'buy'),
            'scenario' => [
                'avg_monthly_income' => $income / $divisor,
                'avg_monthly_expense' => $expense / $divisor,
                'avg_monthly_profit' => $profit / $divisor,
            ],
        ];
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
     * @return Collection<int, object{month_num: int, income: string|float, expense: string|float}>
     */
    private function aggregateMonthlyTotals(int $teamId, Carbon $from, Carbon $to): Collection
    {
        $lineAmount = self::LINE_AMOUNT_SQL;
        $monthSql = $this->sqlMonthExpression('invoices.date');

        return $this->baseItemsQuery($teamId, $from, $to)
            ->selectRaw("{$monthSql} as month_num")
            ->selectRaw("COALESCE(SUM(CASE WHEN invoices.operation = 'sell' THEN {$lineAmount} ELSE 0 END), 0) as income")
            ->selectRaw("COALESCE(SUM(CASE WHEN invoices.operation = 'buy' THEN {$lineAmount} ELSE 0 END), 0) as expense")
            ->groupByRaw($monthSql)
            ->orderByRaw($monthSql)
            ->get()
            ->keyBy(fn (object $row) => (int) $row->month_num);
    }

    /**
     * @param  Collection<int, object{month_num: int, income: string|float, expense: string|float}>  $monthlyRows
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
    private function aggregateCategoryBreakdown(int $teamId, Carbon $from, Carbon $to, string $operation): array
    {
        $lineAmount = self::LINE_AMOUNT_SQL;

        $lines = $this->baseItemsQuery($teamId, $from, $to)
            ->where('invoices.operation', $operation)
            ->selectRaw('invoice_items.category_id as category_id')
            ->selectRaw("{$lineAmount} as line_total")
            ->get();

        if ($lines->isEmpty())
        {
            return [];
        }

        $totalsByCategory = [];
        foreach ($lines as $line)
        {
            $key = $line->category_id !== null ? (int) $line->category_id : 0;
            $totalsByCategory[$key] = ($totalsByCategory[$key] ?? 0.0) + (float) $line->line_total;
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
                'total' => $total,
                'share_percent' => ($total / $grandTotal) * 100,
            ];
        }

        return $breakdown;
    }

    private function baseItemsQuery(int $teamId, Carbon $from, Carbon $to): Builder
    {
        return InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.team_id', $teamId)
            ->whereNotIn('invoices.status', self::EXCLUDED_INVOICE_STATUSES)
            ->whereNotNull('invoices.date')
            ->whereDate('invoices.date', '>=', $from->toDateString())
            ->whereDate('invoices.date', '<=', $to->toDateString());
    }

    private function sqlYearExpression(string $dateColumn): string
    {
        return match (DB::connection()->getDriverName())
        {
            'pgsql' => "EXTRACT(YEAR FROM {$dateColumn})::int",
            'sqlite' => "CAST(strftime('%Y', {$dateColumn}) AS INTEGER)",
            default => "YEAR({$dateColumn})",
        };
    }

    private function sqlMonthExpression(string $dateColumn): string
    {
        return match (DB::connection()->getDriverName())
        {
            'pgsql' => "EXTRACT(MONTH FROM {$dateColumn})::int",
            'sqlite' => "CAST(strftime('%m', {$dateColumn}) AS INTEGER)",
            default => "MONTH({$dateColumn})",
        };
    }
}
