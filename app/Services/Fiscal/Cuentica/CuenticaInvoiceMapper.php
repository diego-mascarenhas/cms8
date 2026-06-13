<?php

namespace App\Services\Fiscal\Cuentica;

use App\Models\Invoice;
use Carbon\Carbon;

class CuenticaInvoiceMapper
{
    /**
     * Build the POST /invoice payload from a local invoice.
     *
     * @return array<string, mixed>
     */
    public function map(Invoice $invoice, int $customerId): array
    {
        $lines = $this->mapLines($invoice);
        $total = $this->round((float) $invoice->total_amount);

        $payload = [
            'issued' => true,
            'customer' => $customerId,
            'date' => $this->invoiceDate($invoice),
            'description' => $this->description($invoice),
            'invoice_lines' => $lines,
            'charges' => [$this->mapCharge($invoice, $total)],
            'tags' => $this->tags($invoice),
        ];

        $serie = trim((string) ($invoice->team?->getSetting('cuentica_invoice_serie', '')
            ?: config('fiscal.platforms.cuentica.invoice_serie', '')));
        if ($serie !== '')
        {
            $payload['serie'] = $serie;
        }

        $reference = trim((string) $invoice->source_reference_id);
        if ($reference !== '')
        {
            $payload['annotations'] = 'Origen: '.($invoice->source_provider ?: 'local').' / '.$reference;
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapLines(Invoice $invoice): array
    {
        $items = $invoice->relationLoaded('items') ? $invoice->items : $invoice->items()->get();

        $sellType = (string) config('fiscal.platforms.cuentica.default_sell_type', 'service');
        $taxRegime = (string) config('fiscal.platforms.cuentica.default_tax_regime', '01');
        $taxSubjection = (string) config('fiscal.platforms.cuentica.default_tax_subjection_code', 'S1');
        $defaultTax = (float) config('fiscal.platforms.cuentica.default_tax_percent', 21);

        if ($items->isNotEmpty())
        {
            return $items->map(fn ($item): array => [
                'quantity' => $this->round((float) ($item->quantity ?: 1), 4),
                'concept' => $this->concept((string) $item->description),
                'amount' => $this->round((float) $item->unit_price, 4),
                'discount' => $this->round((float) ($item->discount ?? 0)),
                'tax' => $this->round((float) ($item->tax_percentage ?? $defaultTax)),
                'sell_type' => $sellType,
                'tax_regime' => $taxRegime,
                'tax_subjection_code' => $taxSubjection,
            ])->values()->all();
        }

        $base = $this->round((float) ($invoice->gross_amount ?: $invoice->total_amount));

        return [[
            'quantity' => 1,
            'concept' => $this->concept($this->description($invoice)),
            'amount' => $base,
            'discount' => $this->round((float) ($invoice->discount ?? 0)),
            'tax' => $defaultTax,
            'sell_type' => $sellType,
            'tax_regime' => $taxRegime,
            'tax_subjection_code' => $taxSubjection,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCharge(Invoice $invoice, float $total): array
    {
        $paid = (float) $invoice->balance <= 0;
        $payments = $invoice->relationLoaded('payments') ? $invoice->payments : $invoice->payments()->get();

        $date = $this->invoiceDate($invoice);
        if ($payments->isNotEmpty())
        {
            $latest = $payments->sortByDesc('date')->first();
            if ($latest && $latest->date)
            {
                $date = Carbon::parse($latest->date)->toDateString();
            }
        }

        return [
            'date' => $date,
            'amount' => $total,
            'payment_method' => (string) config('fiscal.platforms.cuentica.default_payment_method', 'card'),
            'paid' => $paid,
        ];
    }

    private function invoiceDate(Invoice $invoice): string
    {
        return $invoice->date
            ? Carbon::parse($invoice->date)->toDateString()
            : now()->toDateString();
    }

    private function description(Invoice $invoice): string
    {
        $number = trim((string) $invoice->number);

        return $number !== '' ? 'Factura '.$number : 'Factura';
    }

    private function concept(string $value): string
    {
        $value = trim($value);

        return $value !== '' ? $value : 'Servicio';
    }

    /**
     * @return array<int, string>
     */
    private function tags(Invoice $invoice): array
    {
        $tags = ['humano'];

        $provider = trim((string) $invoice->source_provider);
        if ($provider !== '')
        {
            $tags[] = $provider;
        }

        $reference = trim((string) $invoice->source_reference_id);
        if ($reference !== '')
        {
            $tags[] = $reference;
        }

        return $tags;
    }

    private function round(float $value, int $precision = 2): float
    {
        return round($value, $precision);
    }
}
