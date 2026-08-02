<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use Illuminate\Support\Arr;

class StripeInvoiceItemImporter
{
    public function __construct(
        private readonly ServiceSyncImporter $serviceSyncImporter,
    ) {}

    public function syncForInvoice(Invoice $invoice, InvoiceSync $row): void
    {
        $payload = is_array($row->raw_payload) ? $row->raw_payload : [];
        $lines = $this->extractLines($payload);

        if ($lines === [])
        {
            return;
        }

        $categoryId = $this->serviceSyncImporter->resolveCategoryIdForInvoiceItem(
            (int) $invoice->team_id,
            $row->stripe_subscription_id ? (string) $row->stripe_subscription_id : null,
        );

        $invoice->items()->delete();

        foreach ($lines as $line)
        {
            if (! is_array($line))
            {
                continue;
            }

            $attributes = $this->mapLine($line);
            if ($attributes === null)
            {
                continue;
            }

            InvoiceItem::query()->create(array_merge($attributes, [
                'invoice_id' => $invoice->id,
                'category_id' => $categoryId,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractLines(array $payload): array
    {
        $lines = Arr::get($payload, 'lines.data', []);

        return is_array($lines) ? array_values($lines) : [];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{description: string, quantity: float, unit_price: float, discount: float, tax_percentage: float}|null
     */
    private function mapLine(array $line): ?array
    {
        $description = trim((string) (Arr::get($line, 'description') ?? ''));
        $quantity = max(0.0001, (float) (Arr::get($line, 'quantity') ?: 1));

        $amountExcludingTaxCents = $this->resolveAmountExcludingTaxCents($line);
        $discountCents = $this->resolveDiscountCents($line);
        $taxPercentage = $this->resolveTaxPercentage($line, $amountExcludingTaxCents);

        $unitPrice = round(($amountExcludingTaxCents / 100) / $quantity, 2);
        $discount = round($discountCents / 100, 2);

        if ($description === '' && $unitPrice <= 0 && $taxPercentage <= 0)
        {
            return null;
        }

        return [
            'description' => $description !== '' ? $description : '-',
            'quantity' => round($quantity, 2),
            'unit_price' => max(0, $unitPrice),
            'discount' => max(0, $discount),
            'tax_percentage' => round(max(0, $taxPercentage), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function resolveAmountExcludingTaxCents(array $line): float
    {
        $unitExcluding = Arr::get($line, 'unit_amount_excluding_tax');
        $quantity = max(0.0001, (float) (Arr::get($line, 'quantity') ?: 1));

        if (is_numeric($unitExcluding))
        {
            return (float) $unitExcluding * $quantity;
        }

        $amountExcluding = Arr::get($line, 'amount_excluding_tax');
        if (is_numeric($amountExcluding))
        {
            return (float) $amountExcluding;
        }

        $amount = Arr::get($line, 'amount');
        if (is_numeric($amount))
        {
            return (float) $amount;
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function resolveDiscountCents(array $line): float
    {
        $discountAmounts = Arr::get($line, 'discount_amounts', []);
        if (! is_array($discountAmounts) || $discountAmounts === [])
        {
            return 0.0;
        }

        return (float) collect($discountAmounts)->sum(fn ($row) => (float) data_get($row, 'amount', 0));
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function resolveTaxPercentage(array $line, float $amountExcludingTaxCents): float
    {
        $taxRates = Arr::get($line, 'tax_rates', []);
        if (is_array($taxRates) && $taxRates !== [])
        {
            $percentage = (float) collect($taxRates)->sum(fn ($rate) => (float) data_get($rate, 'percentage', 0));
            if ($percentage > 0)
            {
                return $percentage;
            }
        }

        $taxAmountCents = $this->resolveTaxAmountCents($line);
        $taxableCents = $this->resolveTaxableAmountCents($line, $amountExcludingTaxCents);

        if ($taxAmountCents <= 0 || $taxableCents <= 0)
        {
            return 0.0;
        }

        return round(($taxAmountCents / $taxableCents) * 100, 2);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function resolveTaxAmountCents(array $line): float
    {
        $taxes = Arr::get($line, 'taxes', []);
        if (is_array($taxes) && $taxes !== [])
        {
            return (float) collect($taxes)->sum(fn ($row) => (float) data_get($row, 'amount', 0));
        }

        $taxAmounts = Arr::get($line, 'tax_amounts', []);
        if (is_array($taxAmounts) && $taxAmounts !== [])
        {
            return (float) collect($taxAmounts)->sum(fn ($row) => (float) data_get($row, 'amount', 0));
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function resolveTaxableAmountCents(array $line, float $amountExcludingTaxCents): float
    {
        $taxes = Arr::get($line, 'taxes', []);
        if (is_array($taxes) && $taxes !== [])
        {
            $taxable = (float) collect($taxes)->sum(fn ($row) => (float) data_get($row, 'taxable_amount', 0));
            if ($taxable > 0)
            {
                return $taxable;
            }
        }

        $taxAmounts = Arr::get($line, 'tax_amounts', []);
        if (is_array($taxAmounts) && $taxAmounts !== [])
        {
            $taxable = (float) collect($taxAmounts)->sum(fn ($row) => (float) data_get($row, 'taxable_amount', 0));
            if ($taxable > 0)
            {
                return $taxable;
            }
        }

        return $amountExcludingTaxCents;
    }
}
