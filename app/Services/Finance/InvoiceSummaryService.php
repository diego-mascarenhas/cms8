<?php

namespace App\Services\Finance;

use App\Helpers\Helpers;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class InvoiceSummaryService
{
    /** @var list<int> Void, error, draft — excluded from payment summaries. */
    public const EXCLUDED_STATUSES = [3, 7, 9];

    /** @var list<int> */
    public const CREDIT_NOTE_STATUSES = [4, 6];

    /** @var list<int> Bonificada — treated as zero balance, not pending collection. */
    public const BONIFIED_STATUSES = [5, 6];

    /** @var list<int> Not treated as pending collection. */
    public const UNPAID_EXCLUDED_STATUSES = [3, 4, 5, 6, 7, 9];

    /** @var list<int> */
    public const COLLECTED_EXCLUDED_STATUSES = [3, 4, 6, 7, 9];

    /** @var list<string> */
    public const SUMMARY_FILTERS = ['unpaid', 'credit_notes', 'collected', 'overdue'];

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

        return $this->buildMetric($query, 'total_amount');
    }

    /**
     * @return array{count: int, amount_label: string, totals_by_currency: array<string, float>}
     */
    private function buildCollectedMetric(int $teamId): array
    {
        $query = $this->baseQuery($teamId);
        $this->applySummaryFilter($query, 'collected');

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

    public function applySummaryFilter(Builder $query, string $filter): Builder
    {
        return match ($filter)
        {
            'unpaid' => $query
                ->whereNotIn('invoices.status', self::UNPAID_EXCLUDED_STATUSES)
                ->where('invoices.balance', '>', 0),
            'credit_notes' => $query->whereIn('invoices.status', self::CREDIT_NOTE_STATUSES),
            'collected' => $query
                ->whereNotIn('invoices.status', self::COLLECTED_EXCLUDED_STATUSES)
                ->where(function (Builder $inner): void
                {
                    $inner->whereIn('invoices.status', self::BONIFIED_STATUSES)
                        ->orWhere('invoices.balance', '<=', 0);
                }),
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
        return Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId);
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

        $rows = (clone $query)
            ->leftJoin('currencies', 'invoices.currency_id', '=', 'currencies.id')
            ->selectRaw('COALESCE(currencies.code, ?) as currency_code', ['EUR'])
            ->selectRaw("COALESCE(SUM(invoices.{$amountColumn}), 0) as total")
            ->groupBy('currency_code')
            ->get();

        $totalsByCurrency = [];
        foreach ($rows as $row)
        {
            $currency = strtoupper((string) $row->currency_code);
            $totalsByCurrency[$currency] = round((float) $row->total, 2);
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

        ksort($totalsByCurrency);

        $parts = [];
        foreach ($totalsByCurrency as $currency => $amount)
        {
            $parts[] = Helpers::formatMoney($amount, $currency);
        }

        return implode(' · ', $parts);
    }
}
