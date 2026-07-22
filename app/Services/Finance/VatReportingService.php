<?php

namespace App\Services\Finance;

use App\Models\ExchangeRate;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VatReportingService
{
    /**
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    public function currentMonthRange(?Carbon $now = null): array
    {
        $now = $now?->copy() ?? now();

        return [
            'from' => $now->copy()->startOfMonth()->startOfDay(),
            'to' => $now->copy()->endOfMonth()->endOfDay(),
            'label' => $now->copy()->translatedFormat('F Y'),
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    public function previousMonthRange(?Carbon $now = null): array
    {
        $now = ($now?->copy() ?? now())->subMonthNoOverflow();

        return [
            'from' => $now->copy()->startOfMonth()->startOfDay(),
            'to' => $now->copy()->endOfMonth()->endOfDay(),
            'label' => $now->copy()->translatedFormat('F Y'),
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string, quarter: int, year: int}
     */
    public function currentQuarterRange(?Carbon $now = null): array
    {
        $now = $now?->copy() ?? now();
        $quarter = (int) ceil($now->month / 3);
        $year = (int) $now->year;
        $startMonth = ($quarter - 1) * 3 + 1;

        $from = Carbon::create($year, $startMonth, 1)->startOfDay();
        $to = $from->copy()->addMonths(2)->endOfMonth()->endOfDay();

        return [
            'from' => $from,
            'to' => $to,
            'label' => 'Q'.$quarter.' '.$year,
            'quarter' => $quarter,
            'year' => $year,
        ];
    }

    /**
     * Calendar quarter immediately before the current one (useful for tax filing).
     *
     * @return array{from: Carbon, to: Carbon, label: string, quarter: int, year: int}
     */
    public function previousQuarterRange(?Carbon $now = null): array
    {
        $now = $now?->copy() ?? now();
        $currentQuarter = (int) ceil($now->month / 3);
        $year = (int) $now->year;
        $previousQuarter = $currentQuarter - 1;

        if ($previousQuarter < 1)
        {
            $previousQuarter = 4;
            $year--;
        }

        return $this->quarterRange($year, $previousQuarter);
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string, month: int, year: int}
     */
    public function monthRange(int $year, int $month): array
    {
        $month = max(1, min(12, $month));
        $from = Carbon::create($year, $month, 1)->startOfDay();

        return [
            'from' => $from,
            'to' => $from->copy()->endOfMonth()->endOfDay(),
            'label' => $from->copy()->translatedFormat('F Y'),
            'month' => $month,
            'year' => $year,
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string, quarter: int, year: int}
     */
    public function quarterRange(int $year, int $quarter): array
    {
        $quarter = max(1, min(4, $quarter));
        $startMonth = ($quarter - 1) * 3 + 1;
        $from = Carbon::create($year, $startMonth, 1)->startOfDay();
        $to = $from->copy()->addMonths(2)->endOfMonth()->endOfDay();

        return [
            'from' => $from,
            'to' => $to,
            'label' => 'Q'.$quarter.' '.$year,
            'quarter' => $quarter,
            'year' => $year,
        ];
    }

    /**
     * Resolve VAT period from year + a single period token (m:1-12 or q:1-4).
     *
     * @return array{
     *     year: int,
     *     period: string,
     *     mode: 'month'|'quarter',
     *     years: array<int, int>,
     *     range: array{from: Carbon, to: Carbon, label: string},
     *     label: string
     * }
     */
    public function resolveSelectedPeriod(
        ?int $year = null,
        ?string $period = null,
        ?int $teamId = null,
        ?Carbon $now = null,
    ): array {
        $now = $now?->copy() ?? now();
        $years = $this->availableYears($teamId, $now);
        $defaultYear = (int) $now->year;

        $selectedYear = $year ?? $defaultYear;
        if ($years !== [] && ! in_array($selectedYear, $years, true))
        {
            $selectedYear = $years[0];
        }

        $isCurrentYear = $selectedYear === (int) $now->year;
        $parsed = $this->parsePeriodToken($period);

        if ($parsed === null)
        {
            if ($isCurrentYear)
            {
                $previous = $this->previousQuarterRange($now);
                $parsed = [
                    'mode' => 'quarter',
                    'value' => (int) $previous['year'] === $selectedYear
                        ? (int) $previous['quarter']
                        : 1,
                ];
            } else
            {
                $parsed = ['mode' => 'quarter', 'value' => 4];
            }
        }

        if ($parsed['mode'] === 'month')
        {
            $range = $this->monthRange($selectedYear, $parsed['value']);
            $periodToken = 'm:'.$parsed['value'];
        } else
        {
            $range = $this->quarterRange($selectedYear, $parsed['value']);
            $periodToken = 'q:'.$parsed['value'];
        }

        return [
            'year' => $selectedYear,
            'period' => $periodToken,
            'mode' => $parsed['mode'],
            'years' => $years,
            'range' => $range,
            'label' => $range['label'],
        ];
    }

    /**
     * Calendar period immediately before the selected month or quarter.
     *
     * @param  array{mode: string, range: array{from: Carbon, to: Carbon}}  $vatSelection
     * @return array{from: Carbon, to: Carbon}
     */
    public function previousComparableRange(array $vatSelection): array
    {
        $from = $vatSelection['range']['from']->copy();

        if ($vatSelection['mode'] === 'quarter')
        {
            $previousFrom = $from->copy()->subMonthsNoOverflow(3)->startOfMonth()->startOfDay();

            return [
                'from' => $previousFrom,
                'to' => $previousFrom->copy()->addMonths(2)->endOfMonth()->endOfDay(),
            ];
        }

        $previousFrom = $from->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();

        return [
            'from' => $previousFrom,
            'to' => $previousFrom->copy()->endOfMonth()->endOfDay(),
        ];
    }

    /**
     * @return array{mode: 'month'|'quarter', value: int}|null
     */
    public function parsePeriodToken(?string $period): ?array
    {
        $period = strtolower(trim((string) $period));
        if ($period === '')
        {
            return null;
        }

        if (preg_match('/^m:([1-9]|1[0-2])$/', $period, $matches) === 1)
        {
            return ['mode' => 'month', 'value' => (int) $matches[1]];
        }

        if (preg_match('/^q:([1-4])$/', $period, $matches) === 1)
        {
            return ['mode' => 'quarter', 'value' => (int) $matches[1]];
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    public function availableYears(?int $teamId = null, ?Carbon $now = null): array
    {
        $now = $now?->copy() ?? now();
        $teamId ??= auth()->user()?->currentTeam?->id;
        $currentYear = (int) $now->year;

        $query = Invoice::query()->withoutGlobalScopes();

        if ($teamId)
        {
            $query->where('team_id', $teamId);
        }

        $bounds = (clone $query)
            ->whereNotNull('date')
            ->selectRaw('MIN(date) as min_date, MAX(date) as max_date')
            ->first();

        if (! $bounds || blank($bounds->min_date) || blank($bounds->max_date))
        {
            return range($currentYear, $currentYear - 5);
        }

        $minYear = max(2000, (int) Carbon::parse($bounds->min_date)->year);
        $maxYear = min($currentYear + 1, (int) Carbon::parse($bounds->max_date)->year);

        if ($maxYear < $minYear)
        {
            return [$currentYear];
        }

        $years = range($maxYear, $minYear);

        if (! in_array($currentYear, $years, true))
        {
            array_unshift($years, $currentYear);
        }

        return array_values(array_unique($years));
    }

    public function sumIncomeVat(Carbon $from, Carbon $to, string $targetCurrency, ?int $teamId = null): float
    {
        return $this->sumVatForOperation('sell', $from, $to, $targetCurrency, $teamId);
    }

    public function sumExpenseVat(Carbon $from, Carbon $to, string $targetCurrency, ?int $teamId = null): float
    {
        return $this->sumVatForOperation('buy', $from, $to, $targetCurrency, $teamId);
    }

    private function sumVatForOperation(
        string $operation,
        Carbon $from,
        Carbon $to,
        string $targetCurrency,
        ?int $teamId,
    ): float {
        $targetCurrency = strtoupper(trim($targetCurrency));
        $teamId ??= auth()->user()?->currentTeam?->id;

        if (! $teamId || $targetCurrency === '')
        {
            return 0.0;
        }

        $invoices = $this->invoicesQuery($teamId, $operation, $from, $to)
            ->with(['items', 'currency'])
            ->get();

        $total = 0.0;

        foreach ($invoices as $invoice)
        {
            $vatAmount = $this->vatAmountForInvoice($invoice);
            if ($vatAmount == 0.0)
            {
                continue;
            }

            $converted = $this->convertAmount(
                abs($vatAmount),
                $invoice->currency_code,
                $targetCurrency,
                $invoice->date ? Carbon::parse($invoice->date) : $from,
            );

            if ($converted === null)
            {
                continue;
            }

            $total += $invoice->isCreditNote() ? -abs($converted) : $converted;
        }

        return round($total, 2);
    }

    public function invoicesForPeriod(
        int $teamId,
        string $operation,
        Carbon $from,
        Carbon $to,
    ): Builder {
        return $this->invoicesQuery($teamId, $operation, $from, $to);
    }

    public function vatAmountForInvoice(Invoice $invoice): float
    {
        /** @var Collection<int, \App\Models\InvoiceItem> $items */
        $items = $invoice->relationLoaded('items')
            ? $invoice->items
            : $invoice->items()->get();

        $lineTaxTotal = 0.0;
        $hasExplicitTaxRate = false;

        foreach ($items as $item)
        {
            $taxPercentage = (float) $item->tax_percentage;
            if ($taxPercentage > 0)
            {
                $hasExplicitTaxRate = true;
            }

            $lineTaxTotal += (float) $item->tax_amount;
        }

        if ($hasExplicitTaxRate)
        {
            $tax = round(abs($lineTaxTotal), 2);
        } else
        {
            // Sell and buy: when lines lack tax %, derive IVA from total − base
            // (Stripe/Cuéntica Spain invoices often store base in gross_amount).
            $diff = (float) $invoice->total_amount - (float) $invoice->gross_amount;
            $tax = round(max(0, $diff), 2);
        }

        if ($invoice->isCreditNote())
        {
            return round(-$tax, 2);
        }

        return $tax;
    }

    public function convertAmount(float $amount, string $fromCurrency, string $toCurrency, Carbon $date): ?float
    {
        $fromCurrency = strtoupper(trim($fromCurrency));
        $toCurrency = strtoupper(trim($toCurrency));

        if ($fromCurrency === '' || $toCurrency === '')
        {
            return null;
        }

        if ($fromCurrency === $toCurrency)
        {
            return round($amount, 2);
        }

        return ExchangeRate::convertOnOrBeforeDate($amount, $fromCurrency, $toCurrency, $date);
    }

    private function invoicesQuery(int $teamId, string $operation, Carbon $from, Carbon $to): Builder
    {
        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('operation', $operation)
            ->whereNotIn('status', [3, 7, 9])
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString());
    }
}
