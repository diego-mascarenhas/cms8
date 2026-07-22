<?php

namespace App\Services\Finance;

use App\Models\ExchangeRate;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VatHaciendaCsvExportService
{
    public function __construct(
        private readonly VatReportingService $vatReportingService,
        private readonly PaymentReportingCurrencyService $paymentReportingCurrencyService,
    ) {}

    public function download(
        string $operation,
        Carbon $from,
        Carbon $to,
        string $periodLabel,
        ?int $teamId = null,
        ?string $targetCurrency = null,
    ): StreamedResponse {
        $teamId ??= (int) auth()->user()->currentTeam->id;
        $targetCurrency = strtoupper($targetCurrency
            ?? $this->paymentReportingCurrencyService->reportingCurrencyForCurrentTeam());

        $slug = $operation === 'buy' ? 'gastos' : 'ingresos';
        $periodSlug = Str::slug($periodLabel) ?: $from->format('Y-m');
        $fileName = 'hacienda-'.$slug.'-'.$periodSlug.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($teamId, $operation, $from, $to, $targetCurrency)
        {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Comprobante',
                'Fecha',
                'Razón Social',
                'ID Fiscal',
                'Importe',
                'Moneda',
                'Cambio',
                'Importe ('.$targetCurrency.')',
                'Tax ('.$targetCurrency.')',
                'Total ('.$targetCurrency.')',
                'País',
                'Estado',
                'Link',
            ]);

            $totals = [
                'subtotal' => 0.0,
                'tax' => 0.0,
                'total' => 0.0,
                'rows' => 0,
            ];

            $this->vatReportingService
                ->invoicesForPeriod($teamId, $operation, $from, $to)
                ->with([
                    'items',
                    'currency',
                    'enterprise',
                    'billingAddress',
                    'stripeInvoiceSync',
                ])
                ->orderBy('date')
                ->orderBy('id')
                ->chunk(200, function ($invoices) use ($handle, $targetCurrency, $from, &$totals)
                {
                    foreach ($invoices as $invoice)
                    {
                        [$row, $converted] = $this->rowForInvoice($invoice, $targetCurrency, $from);
                        fputcsv($handle, $row);

                        if ($converted['subtotal'] !== null)
                        {
                            $totals['subtotal'] += $converted['subtotal'];
                        }
                        if ($converted['tax'] !== null)
                        {
                            $totals['tax'] += $converted['tax'];
                        }
                        if ($converted['total'] !== null)
                        {
                            $totals['total'] += $converted['total'];
                        }
                        $totals['rows']++;
                    }
                });

            fputcsv($handle, [
                'TOTALES',
                '',
                '',
                '',
                '',
                '',
                '',
                number_format(round($totals['subtotal'], 2), 2, ',', '.'),
                number_format(round($totals['tax'], 2), 2, ',', '.'),
                number_format(round($totals['total'], 2), 2, ',', '.'),
                '',
                $totals['rows'].' registros',
                '',
            ]);

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    /**
     * @return array{0: list<string>, 1: array{subtotal: ?float, tax: ?float, total: ?float}}
     */
    private function rowForInvoice(Invoice $invoice, string $targetCurrency, Carbon $fallbackDate): array
    {
        $currency = strtoupper((string) $invoice->currency_code);
        // Fiscal conversion uses the invoice issue date (same as Stripe Hacienda CSV).
        $invoiceDate = $invoice->date ? Carbon::parse($invoice->date) : $fallbackDate;
        $total = (float) $invoice->total_amount;
        $tax = $this->vatReportingService->vatAmountForInvoice($invoice);
        $subtotal = round($total - $tax, 2);

        $exchangeRateDisplay = '';
        $subtotalTarget = null;
        $taxTarget = null;
        $totalTarget = null;

        if ($currency === $targetCurrency)
        {
            $subtotalTarget = $subtotal;
            $taxTarget = $tax;
            $totalTarget = $total;
        } else
        {
            // Rate is foreign → reporting currency on the invoice date.
            $rateToTarget = ExchangeRate::rateOnOrBeforeDate($currency, $targetCurrency, $invoiceDate);

            if ($rateToTarget !== null && $rateToTarget > 0)
            {
                // Display like Stripe: reporting → foreign (inverted).
                $exchangeRateDisplay = number_format(1 / $rateToTarget, 4, ',', '.');
                $subtotalTarget = round($subtotal * $rateToTarget, 2);
                $taxTarget = round($tax * $rateToTarget, 2);
                $totalTarget = round($total * $rateToTarget, 2);
            } else
            {
                $exchangeRateDisplay = 'N/A';
            }
        }

        $row = [
            (string) ($invoice->number ?? ''),
            $invoiceDate->format('d/m/Y'),
            $this->resolveEnterpriseName($invoice),
            $this->resolveTaxId($invoice),
            number_format($total, 2, ',', '.'),
            $currency,
            $exchangeRateDisplay,
            $subtotalTarget !== null ? number_format($subtotalTarget, 2, ',', '.') : '',
            $taxTarget !== null ? number_format($taxTarget, 2, ',', '.') : '',
            $totalTarget !== null ? number_format($totalTarget, 2, ',', '.') : '',
            $this->resolveCountry($invoice),
            (string) $invoice->status_label,
            $this->resolveLink($invoice),
        ];

        return [$row, [
            'subtotal' => $subtotalTarget,
            'tax' => $taxTarget,
            'total' => $totalTarget,
        ]];
    }

    private function resolveEnterpriseName(Invoice $invoice): string
    {
        $syncName = trim((string) ($invoice->stripeInvoiceSync?->customer_name ?? ''));
        if ($syncName !== '')
        {
            return $syncName;
        }

        return (string) ($invoice->enterprise?->name ?? '');
    }

    private function resolveTaxId(Invoice $invoice): string
    {
        $billingTaxId = trim((string) ($invoice->billingAddress?->identification_number ?? ''));
        if ($billingTaxId !== '')
        {
            return $this->cleanTaxId($billingTaxId);
        }

        $syncTaxId = trim((string) ($invoice->stripeInvoiceSync?->customer_tax_id ?? ''));

        return $this->cleanTaxId($syncTaxId);
    }

    private function cleanTaxId(string $taxId): string
    {
        if ($taxId === '')
        {
            return '';
        }

        if (preg_match('/^(.+?)\s*\(([^)]+)\)$/', $taxId, $matches) === 1)
        {
            return trim($matches[1]);
        }

        if (preg_match('/^([\d\-]+)([a-z_]+)$/i', $taxId, $matches) === 1)
        {
            return trim($matches[1]);
        }

        return $taxId;
    }

    private function resolveCountry(Invoice $invoice): string
    {
        $billingCountry = trim((string) ($invoice->billingAddress?->country ?? ''));
        if ($billingCountry !== '')
        {
            return strtoupper($billingCountry);
        }

        $syncCountry = trim((string) ($invoice->stripeInvoiceSync?->customer_address_country ?? ''));
        if ($syncCountry !== '')
        {
            return strtoupper($syncCountry);
        }

        return strtoupper(trim((string) ($invoice->enterprise?->country ?? '')));
    }

    private function resolveLink(Invoice $invoice): string
    {
        return (string) (
            $invoice->stripeInvoicePdfUrl()
            ?? $invoice->stripeHostedInvoiceUrl()
            ?? route('invoice.show', $invoice->id)
        );
    }
}
