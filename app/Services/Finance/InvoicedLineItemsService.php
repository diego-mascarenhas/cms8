<?php

namespace App\Services\Finance;

use App\Models\InvoiceItem;
use App\Support\InvoiceSettlementConverter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InvoicedLineItemsService
{
    public function __construct(
        private readonly PaymentReportingCurrencyService $reportingCurrencyService,
        private readonly SubscriptionInvoiceCategoryResolver $subscriptionInvoiceCategoryResolver,
    ) {}

    /**
     * @return array{year: int, month: int, from: Carbon, to: Carbon}
     */
    public function resolvePeriodFilter(Request $request): array
    {
        $year = max(1, (int) $request->query('year', Carbon::now()->year));
        $month = max(0, min(12, (int) $request->query('month', 0)));

        if ($month > 0)
        {
            $from = Carbon::create($year, $month, 1)->startOfDay();
            $to = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        } else
        {
            $from = Carbon::create($year, 1, 1)->startOfDay();
            $to = Carbon::create($year, 12, 31)->endOfDay();
        }

        return [
            'year' => $year,
            'month' => $month,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @return list<int>
     */
    public function availableYearsForTeam(int $teamId): array
    {
        $bounds = app(InvoiceAnalyticsService::class)->resolveYearBounds($teamId);

        return range($bounds['max'], $bounds['min']);
    }

    /**
     * @return Collection<int, InvoiceItem>
     */
    public function queryItems(
        int $teamId,
        Carbon $from,
        Carbon $to,
        ?string $operation = null,
        ?int $categoryId = null,
        bool $uncategorizedOnly = false,
    ): Collection {
        return InvoiceItem::query()
            ->when($uncategorizedOnly, fn (Builder $query) => $query->whereNull('category_id'))
            ->when(! $uncategorizedOnly && $categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->whereHas('invoice', function ($query) use ($teamId, $operation, $from, $to): void
            {
                $query->withoutGlobalScopes()
                    ->where('team_id', $teamId)
                    ->when($operation !== null, fn (Builder $invoiceQuery) => $invoiceQuery->where('operation', $operation))
                    ->whereNotIn('status', InvoiceAnalyticsService::EXCLUDED_INVOICE_STATUSES)
                    ->whereNotNull('date')
                    ->whereDate('date', '>=', $from->toDateString())
                    ->whereDate('date', '<=', $to->toDateString());
            })
            ->with(['invoice.enterprise', 'invoice.currency', 'invoice.payments.account.currency', 'category'])
            ->get()
            ->sortBy(
                fn (InvoiceItem $item) => mb_strtolower((string) ($item->invoice?->enterprise?->name ?? '')),
                SORT_NATURAL,
            )
            ->values();
    }

    /**
     * @return array{
     *     lines: list<array{
     *         id: int,
     *         invoice_id: int|null,
     *         invoice_number: string|null,
     *         enterprise_id: int|null,
     *         enterprise_name: string,
     *         description: string|null,
     *         category_id: int|null,
     *         category_name: string|null,
     *         subscription_type: string|null,
     *         category_group: string|null,
     *         amount: float,
     *         has_discount: bool,
     *         discount_amount: float|null,
     *     }>,
     *     total: float,
     *     reporting_currency: string,
     *     conversion_complete: bool,
     * }
     */
    public function buildDisplayPayload(Collection $items, int $teamId, bool $showDescription, bool $showCategory): array
    {
        $reportingCurrency = $this->reportingCurrencyService->reportingCurrencyForTeam($teamId);
        $hints = $showCategory ? $this->subscriptionInvoiceCategoryResolver->hintsFor($items) : [];
        $lines = [];
        $total = 0.0;
        $conversionComplete = true;

        foreach ($items as $item)
        {
            $invoice = $item->invoice;
            $lineNet = $this->lineNetAmount($item);
            $converted = $this->convertedAmount($item, $lineNet, $reportingCurrency);

            if ($converted === null)
            {
                $conversionComplete = false;

                continue;
            }

            $discount = (float) ($item->discount ?? 0);
            $hasDiscount = $discount > 0;
            $convertedDiscount = $hasDiscount
                ? $this->convertedAmount($item, $discount, $reportingCurrency)
                : null;

            $hint = $hints[(int) $item->id] ?? null;
            $categoryId = $item->category_id ? (int) $item->category_id : null;
            $categoryName = $item->category?->name;
            if (is_array($hint) && ! empty($hint['category_id']))
            {
                $categoryId = (int) $hint['category_id'];
                $categoryName = $hint['category_name'] ?: $categoryName;
            }

            $lines[] = [
                'id' => (int) $item->id,
                'invoice_id' => $invoice?->id ? (int) $invoice->id : null,
                'invoice_number' => filled($invoice?->number) ? (string) $invoice->number : null,
                'enterprise_id' => $invoice?->enterprise_id ? (int) $invoice->enterprise_id : null,
                'enterprise_name' => (string) ($invoice?->enterprise?->name ?? __('Unknown')),
                'description' => $showDescription ? (string) $item->description : null,
                'category_id' => $categoryId,
                'category_name' => $showCategory
                    ? (string) ($categoryName ?: __('Uncategorized'))
                    : null,
                'subscription_type' => is_array($hint) ? $hint['type'] : null,
                'category_group' => is_array($hint) ? $hint['group'] : null,
                'amount' => $converted,
                'has_discount' => $hasDiscount,
                'discount_amount' => $convertedDiscount,
            ];

            $total += $converted;
        }

        return [
            'lines' => $lines,
            'total' => round($total, 2),
            'reporting_currency' => $reportingCurrency,
            'conversion_complete' => $conversionComplete && $lines !== [],
        ];
    }

    private function lineNetAmount(InvoiceItem $item): float
    {
        return round(
            ((float) $item->quantity * (float) $item->unit_price) - (float) ($item->discount ?? 0),
            2,
        );
    }

    private function convertedAmount(InvoiceItem $item, float $amount, string $reportingCurrency): ?float
    {
        $invoice = $item->invoice;

        if (! $invoice || blank($invoice->date))
        {
            return null;
        }

        return InvoiceSettlementConverter::convert(
            $amount,
            (string) $invoice->currency_code,
            $reportingCurrency,
            $invoice->date,
            $invoice,
        );
    }
}
