<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Services\Finance\CreditNoteNumberAllocator;
use App\Services\Finance\InvoiceCurrencyService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StripeCreditNoteCoreImportService
{
    public function __construct(
        private readonly InvoiceCurrencyService $currencyService,
        private readonly CreditNoteNumberAllocator $creditNoteNumberAllocator,
        private readonly StripeInvoiceSyncUpserter $invoiceSyncUpserter,
    ) {}

    /**
     * Import a Stripe credit note as its own local invoice document.
     * The original invoice is never modified by this method.
     *
     * @param  array<string, mixed>  $creditNotePayload
     */
    public function importFromStripePayload(int $teamId, array $creditNotePayload, Invoice $originalInvoice): ?Invoice
    {
        $externalId = trim((string) Arr::get($creditNotePayload, 'id'));
        if ($externalId === '' || ! str_starts_with($externalId, 'cn_'))
        {
            return null;
        }

        if ((bool) Arr::get($creditNotePayload, 'voided', false))
        {
            return null;
        }

        $status = (string) Arr::get($creditNotePayload, 'status', 'issued');
        if ($status === 'void')
        {
            return null;
        }

        $subtotal = $this->centsToMajor(
            Arr::get($creditNotePayload, 'subtotal_excluding_tax')
            ?? Arr::get($creditNotePayload, 'subtotal'),
        );
        $total = $this->centsToMajor(
            Arr::get($creditNotePayload, 'total')
            ?? Arr::get($creditNotePayload, 'amount'),
        );
        $tax = $this->resolveTaxMajor($creditNotePayload, $subtotal, $total);
        $discount = $this->centsToMajor(Arr::get($creditNotePayload, 'discount_amount')) ?? 0.0;

        if ($subtotal === null && $total !== null && $tax !== null)
        {
            $subtotal = round(max(0, $total - $tax), 2);
        }

        $subtotal ??= $total ?? 0.0;
        $total ??= $subtotal;
        $tax ??= round(max(0, $total - $subtotal), 2);

        $stripeNumber = trim((string) (Arr::get($creditNotePayload, 'number') ?? ''));
        if ($stripeNumber === '')
        {
            $stripeNumber = 'CN-'.Str::upper(Str::substr($externalId, -8));
        }

        $existing = Invoice::withoutGlobalScopes()
            ->where('source_provider', 'stripe')
            ->where('source_reference_id', $externalId)
            ->first();

        $number = $existing && $this->creditNoteNumberAllocator->isHumanoCreditNoteNumber((string) $existing->number)
            ? (string) $existing->number
            : $this->creditNoteNumberAllocator->next(
                $teamId,
                $this->creditNoteNumberAllocator->seriePrefixFromInvoiceNumber($originalInvoice->number),
            );

        $payload = [
            'team_id' => $teamId,
            'enterprise_id' => $originalInvoice->enterprise_id,
            'billing_id' => $originalInvoice->billing_id,
            'type_id' => 2,
            'operation' => 'sell',
            'number' => $number,
            'date' => $this->resolveDate($creditNotePayload),
            'due_date' => null,
            'gross_amount' => abs($subtotal),
            'discount' => abs($discount),
            'total_amount' => abs($total),
            'balance' => 0,
            'status' => 4,
            'source_provider' => 'stripe',
            'source_reference_id' => $externalId,
            'source_synced_at' => now(),
        ];

        if (Schema::hasColumn('invoices', 'currency_id'))
        {
            $payload['currency_id'] = $originalInvoice->currency_id
                ?? $this->currencyService->defaultCurrencyId();
        }

        // Keep Stripe's document number on invoice_syncs (no invoices.external_number column).
        $syncPayload = $creditNotePayload;
        $syncPayload['number'] = $stripeNumber;
        if (! isset($syncPayload['customer']) && filled($originalInvoice->source_reference_id))
        {
            $syncPayload['customer'] = data_get(
                $originalInvoice->stripeInvoiceSync?->raw_payload,
                'customer',
            ) ?? $originalInvoice->stripeInvoiceSync?->customer_id;
        }
        $this->invoiceSyncUpserter->upsertFromPayload($teamId, $syncPayload);

        if ($existing)
        {
            $existing->fill($payload);
            $existing->save();
            $this->syncItems($existing, $creditNotePayload, $subtotal, $tax);

            return $existing->fresh(['items']);
        }

        $invoice = Invoice::withoutGlobalScopes()->create($payload);
        $this->syncItems($invoice, $creditNotePayload, $subtotal, $tax);

        return $invoice->fresh(['items']);
    }

    /**
     * @param  array<string, mixed>  $creditNotePayload
     */
    private function syncItems(Invoice $invoice, array $creditNotePayload, float $subtotal, float $tax): void
    {
        $invoice->items()->delete();

        $lines = Arr::get($creditNotePayload, 'lines.data', []);
        if (! is_array($lines) || $lines === [])
        {
            $taxPercentage = $subtotal > 0 ? round(($tax / $subtotal) * 100, 2) : 0.0;

            $invoice->items()->create([
                'description' => trim((string) (Arr::get($creditNotePayload, 'memo') ?? 'Nota de crédito')) ?: 'Nota de crédito',
                'quantity' => 1,
                'unit_price' => abs($subtotal),
                'discount' => 0,
                'tax_percentage' => max(0, $taxPercentage),
            ]);

            return;
        }

        foreach ($lines as $line)
        {
            if (! is_array($line))
            {
                continue;
            }

            $mapped = $this->mapLine($line, $subtotal, $tax);
            if ($mapped === null)
            {
                continue;
            }

            $invoice->items()->create($mapped);
        }
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{description: string, quantity: float, unit_price: float, discount: float, tax_percentage: float}|null
     */
    private function mapLine(array $line, float $documentSubtotal, float $documentTax): ?array
    {
        $description = trim((string) (Arr::get($line, 'description') ?? ''));
        $quantity = max(0.0001, (float) (Arr::get($line, 'quantity') ?: 1));

        $amountExcludingCents = Arr::get($line, 'amount_excluding_tax');
        if (! is_numeric($amountExcludingCents))
        {
            $amountExcludingCents = Arr::get($line, 'amount');
        }

        $unitPrice = is_numeric($amountExcludingCents)
            ? round((abs((float) $amountExcludingCents) / 100) / $quantity, 2)
            : 0.0;

        $taxAmounts = Arr::get($line, 'tax_amounts', []);
        $lineTaxCents = 0.0;
        if (is_array($taxAmounts))
        {
            foreach ($taxAmounts as $taxAmount)
            {
                $lineTaxCents += abs((float) Arr::get($taxAmount, 'amount', 0));
            }
        }

        $lineNet = $unitPrice * $quantity;
        $taxPercentage = 0.0;
        if ($lineNet > 0 && $lineTaxCents > 0)
        {
            $taxPercentage = round((($lineTaxCents / 100) / $lineNet) * 100, 2);
        } elseif ($documentSubtotal > 0 && $documentTax > 0)
        {
            $taxPercentage = round(($documentTax / $documentSubtotal) * 100, 2);
        }

        if ($description === '' && $unitPrice <= 0)
        {
            return null;
        }

        return [
            'description' => $description !== '' ? $description : 'Nota de crédito',
            'quantity' => round($quantity, 2),
            'unit_price' => max(0, $unitPrice),
            'discount' => 0,
            'tax_percentage' => max(0, $taxPercentage),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTaxMajor(array $payload, ?float $subtotal, ?float $total): ?float
    {
        $tax = $this->centsToMajor(Arr::get($payload, 'tax'));
        if ($tax !== null && $tax > 0)
        {
            return $tax;
        }

        $totalTaxAmounts = Arr::get($payload, 'total_tax_amounts', []);
        if (is_array($totalTaxAmounts) && $totalTaxAmounts !== [])
        {
            $sumCents = 0.0;
            foreach ($totalTaxAmounts as $entry)
            {
                $sumCents += abs((float) Arr::get($entry, 'amount', 0));
            }

            if ($sumCents > 0)
            {
                return round($sumCents / 100, 2);
            }
        }

        if ($subtotal !== null && $total !== null && $total > $subtotal)
        {
            return round($total - $subtotal, 2);
        }

        return $tax;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDate(array $payload): string
    {
        $created = Arr::get($payload, 'created');
        if (is_numeric($created))
        {
            return Carbon::createFromTimestampUTC((int) $created)
                ->setTimezone(config('app.timezone'))
                ->toDateString();
        }

        return now()->toDateString();
    }

    private function centsToMajor(mixed $amount): ?float
    {
        if (! is_numeric($amount))
        {
            return null;
        }

        return round(abs((float) $amount) / 100, 2);
    }
}
