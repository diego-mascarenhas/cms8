<?php

namespace App\Services\Fiscal\Cuentica;

use App\Enums\CuenticaInboundDocumentKind;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use Illuminate\Support\Arr;

class CuenticaInvoiceItemImporter
{
    public function syncForInvoice(Invoice $invoice, InvoiceSync $row, CuenticaInboundDocumentKind $kind): void
    {
        $payload = is_array($row->raw_payload) ? $row->raw_payload : [];
        $lines = $this->extractLines($payload, $kind);

        if ($lines === [])
        {
            return;
        }

        $invoice->items()->delete();

        foreach ($lines as $line)
        {
            if (! is_array($line))
            {
                continue;
            }

            $attributes = $this->mapLine($line, $kind);
            if ($attributes === null)
            {
                continue;
            }

            InvoiceItem::query()->create(array_merge($attributes, [
                'invoice_id' => $invoice->id,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractLines(array $payload, CuenticaInboundDocumentKind $kind): array
    {
        $key = $kind === CuenticaInboundDocumentKind::Sale ? 'invoice_lines' : 'expense_lines';
        $lines = Arr::get($payload, $key, []);

        return is_array($lines) ? array_values($lines) : [];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{description: string, quantity: float, unit_price: float, discount: float, tax_percentage: float}|null
     */
    private function mapLine(array $line, CuenticaInboundDocumentKind $kind): ?array
    {
        if ($kind === CuenticaInboundDocumentKind::Sale)
        {
            $description = trim((string) (Arr::get($line, 'concept') ?? ''));
            $quantity = max(0.0001, (float) (Arr::get($line, 'quantity') ?: 1));
            $unitPrice = (float) (Arr::get($line, 'amount') ?? 0);

            if ($description === '' && $unitPrice <= 0)
            {
                return null;
            }

            return [
                'description' => $description !== '' ? $description : '-',
                'quantity' => round($quantity, 2),
                'unit_price' => round(max(0, $unitPrice), 2),
                'discount' => round(max(0, (float) (Arr::get($line, 'discount') ?? 0)), 2),
                'tax_percentage' => round(max(0, (float) (Arr::get($line, 'tax') ?? 21)), 2),
            ];
        }

        $description = trim((string) (Arr::get($line, 'description') ?? ''));
        $unitPrice = (float) (Arr::get($line, 'base') ?? 0);

        if ($description === '' && $unitPrice <= 0)
        {
            return null;
        }

        return [
            'description' => $description !== '' ? $description : '-',
            'quantity' => 1,
            'unit_price' => round(max(0, $unitPrice), 2),
            'discount' => 0,
            'tax_percentage' => round(max(0, (float) (Arr::get($line, 'tax') ?? 21)), 2),
        ];
    }
}
