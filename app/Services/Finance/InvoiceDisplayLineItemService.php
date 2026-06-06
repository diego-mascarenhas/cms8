<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use Illuminate\Support\Collection;

class InvoiceDisplayLineItemService
{
    /**
     * @return Collection<int, array{
     *     description: string,
     *     quantity: float,
     *     unit_price: float,
     *     discount: float,
     *     total: float,
     * }>
     */
    public function forInvoice(Invoice $invoice): Collection
    {
        if ($invoice->relationLoaded('items') && $invoice->items->isNotEmpty())
        {
            return $invoice->items->map(fn (InvoiceItem $item): array => $this->fromInvoiceItem($item));
        }

        if ($invoice->items()->exists())
        {
            return $invoice->items()->get()->map(fn (InvoiceItem $item): array => $this->fromInvoiceItem($item));
        }

        return $this->fromStripeInvoiceSync($invoice);
    }

    /**
     * @return array{description: string, quantity: float, unit_price: float, discount: float, total: float}
     */
    private function fromInvoiceItem(InvoiceItem $item): array
    {
        $quantity = (float) ($item->quantity ?: 1);
        $unitPrice = (float) $item->unit_price;
        $discount = (float) ($item->discount ?? 0);

        return [
            'description' => (string) ($item->description ?? $item->category?->name ?? '-'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'total' => round(max(0, ($unitPrice * $quantity) - $discount), 2),
        ];
    }

    /**
     * @return Collection<int, array{
     *     description: string,
     *     quantity: float,
     *     unit_price: float,
     *     discount: float,
     *     total: float,
     * }>
     */
    private function fromStripeInvoiceSync(Invoice $invoice): Collection
    {
        if ($invoice->source_provider !== 'stripe' || ! filled($invoice->source_reference_id))
        {
            return collect();
        }

        $sync = InvoiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $invoice->source_reference_id)
            ->first();

        if (! $sync)
        {
            return collect();
        }

        $currency = strtoupper((string) ($sync->currency ?: $invoice->currency_code));
        $lines = data_get($sync->raw_payload, 'lines.data', []);

        if (! is_array($lines) || $lines === [])
        {
            return collect();
        }

        return collect($lines)
            ->filter(fn ($line): bool => is_array($line))
            ->map(fn (array $line): array => $this->fromStripeLine($line, $currency))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{description: string, quantity: float, unit_price: float, discount: float, total: float}
     */
    private function fromStripeLine(array $line, string $currency): array
    {
        $divisor = $this->amountDivisor($currency);
        $quantity = max(1.0, (float) (data_get($line, 'quantity') ?: 1));

        $unitAmountCents = data_get($line, 'price.unit_amount')
            ?? data_get($line, 'plan.amount')
            ?? data_get($line, 'unit_amount_excluding_tax');

        if ($unitAmountCents !== null && $unitAmountCents !== '')
        {
            $unitPrice = round(((float) $unitAmountCents) / $divisor, 2);
        } else
        {
            $lineAmount = round(((float) data_get($line, 'amount', 0)) / $divisor, 2);
            $unitPrice = round($lineAmount / $quantity, 2);
        }

        $discount = round(
            collect(data_get($line, 'discount_amounts', []))
                ->sum(fn ($entry): float => (float) data_get($entry, 'amount', 0)) / $divisor,
            2,
        );

        return [
            'description' => (string) (data_get($line, 'description') ?: '-'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'total' => round(max(0, ($unitPrice * $quantity) - $discount), 2),
        ];
    }

    private function amountDivisor(string $currency): int
    {
        $zeroDecimal = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        return in_array(strtoupper($currency), $zeroDecimal, true) ? 1 : 100;
    }
}
