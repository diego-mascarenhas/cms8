<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class StripeCreditNoteCreatePayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $externalId, Invoice $invoice, string $reason, mixed $stripeInvoice): array
    {
        $payload = [
            'invoice' => $externalId,
            'reason' => $reason,
            'metadata' => [
                'humano_invoice_id' => (string) $invoice->id,
                'humano_team_id' => (string) $invoice->team_id,
            ],
        ];

        $lines = $this->invoiceLineCreditLines($stripeInvoice);
        if ($lines !== [])
        {
            $payload['lines'] = $lines;

            return $payload;
        }

        $amountCents = $this->resolveAmountCents($stripeInvoice, $invoice);
        if ($amountCents <= 0)
        {
            throw ValidationException::withMessages([
                'reason' => __('invoice_credit_note.errors.no_creditable_amount'),
            ]);
        }

        $payload['amount'] = $amountCents;

        return $payload;
    }

    /**
     * @return list<array{type: string, invoice_line_item: string, quantity: float|int}>
     */
    private function invoiceLineCreditLines(mixed $stripeInvoice): array
    {
        $lineItems = data_get($stripeInvoice, 'lines.data', []);
        if (! is_array($lineItems))
        {
            return [];
        }

        $lines = [];
        foreach ($lineItems as $line)
        {
            if (! is_array($line))
            {
                continue;
            }

            $lineId = data_get($line, 'id');
            if (! is_string($lineId) || ! str_starts_with($lineId, 'il_'))
            {
                continue;
            }

            $lines[] = [
                'type' => 'invoice_line_item',
                'invoice_line_item' => $lineId,
                'quantity' => data_get($line, 'quantity', 1),
            ];
        }

        return $lines;
    }

    private function resolveAmountCents(mixed $stripeInvoice, Invoice $invoice): int
    {
        $currency = strtoupper((string) (data_get($stripeInvoice, 'currency') ?: $invoice->currency_code));
        $divisor = $this->amountDivisor($currency);

        foreach ([
            data_get($stripeInvoice, 'post_payment_credit_notes_amount'),
            data_get($stripeInvoice, 'amount_remaining'),
            data_get($stripeInvoice, 'total'),
        ] as $candidate)
        {
            if ($candidate !== null && $candidate !== '' && (int) $candidate > 0)
            {
                return (int) $candidate;
            }
        }

        $totalMajor = round((float) ($invoice->total_amount ?? 0), 2);
        if ($totalMajor <= 0)
        {
            return 0;
        }

        return (int) round($totalMajor * $divisor);
    }

    private function amountDivisor(string $currency): int
    {
        $zeroDecimal = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        return in_array(strtoupper($currency), $zeroDecimal, true) ? 1 : 100;
    }
}
