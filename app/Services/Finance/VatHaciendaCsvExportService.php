<?php

namespace App\Services\Finance;

use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class VatHaciendaCsvExportService
{
    public function __construct(
        private readonly VatReportingService $vatReportingService,
        private readonly PaymentReportingCurrencyService $paymentReportingCurrencyService,
        private readonly StripeInvoicePdfRefresher $stripeInvoicePdfRefresher,
    ) {}

    /** @var array<int, string|null> */
    private array $shareHashes = [];

    public function download(
        string $operation,
        Carbon $from,
        Carbon $to,
        string $periodLabel,
        ?int $teamId = null,
        ?string $targetCurrency = null,
        string $documentScope = 'invoices',
    ): StreamedResponse {
        $teamId ??= (int) auth()->user()->currentTeam->id;
        $targetCurrency = strtoupper($targetCurrency
            ?? $this->paymentReportingCurrencyService->reportingCurrencyForCurrentTeam());

        $documentScope = in_array($documentScope, ['invoices', 'credit_notes'], true)
            ? $documentScope
            : 'invoices';

        $slug = $operation === 'buy' ? 'gastos' : 'ingresos';
        if ($documentScope === 'credit_notes')
        {
            $slug = 'notas-credito';
        }
        $periodSlug = Str::slug($periodLabel) ?: $from->format('Y-m');
        $fileName = 'hacienda-'.$slug.'-'.$periodSlug.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($teamId, $operation, $from, $to, $targetCurrency, $documentScope)
        {
            $handle = fopen('php://output', 'w');
            $this->writeCsv($handle, $teamId, $operation, $from, $to, $targetCurrency, $documentScope);
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    /**
     * Previous-quarter Hacienda books: venta, compra, and credit notes for both.
     */
    public function downloadPreviousQuarterZip(?int $teamId = null): BinaryFileResponse
    {
        $teamId ??= (int) auth()->user()->currentTeam->id;
        $quarter = $this->vatReportingService->previousQuarterRange();

        return $this->downloadPeriodZip(
            $teamId,
            $quarter['from'],
            $quarter['to'],
            'Q'.$quarter['quarter'].'-'.$quarter['year'],
            strtoupper($this->paymentReportingCurrencyService->reportingCurrencyForCurrentTeam()),
        );
    }

    public function downloadPeriodZip(
        int $teamId,
        Carbon $from,
        Carbon $to,
        string $periodSlug,
        string $targetCurrency,
    ): BinaryFileResponse {
        $entries = [
            ['operation' => 'sell', 'documentScope' => 'invoices', 'slug' => 'venta'],
            ['operation' => 'buy', 'documentScope' => 'invoices', 'slug' => 'compra'],
            ['operation' => 'sell', 'documentScope' => 'credit_notes', 'slug' => 'notas-credito-venta'],
            ['operation' => 'buy', 'documentScope' => 'credit_notes', 'slug' => 'notas-credito-compra'],
        ];

        $temporaryPath = tempnam(sys_get_temp_dir(), 'hacienda-zip-');
        if ($temporaryPath === false)
        {
            throw new RuntimeException('Unable to create temporary file for Hacienda ZIP.');
        }

        $zip = new ZipArchive;
        if ($zip->open($temporaryPath, ZipArchive::OVERWRITE) !== true)
        {
            throw new RuntimeException('Unable to create Hacienda ZIP.');
        }

        foreach ($entries as $entry)
        {
            $csv = $this->renderCsv(
                $teamId,
                $entry['operation'],
                $from,
                $to,
                $targetCurrency,
                $entry['documentScope'],
            );
            $zip->addFromString('hacienda-'.$entry['slug'].'-'.$periodSlug.'.csv', $csv);
        }

        $zip->close();

        return response()->download($temporaryPath, 'hacienda-compra-venta-'.$periodSlug.'.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return list<string>
     */
    public function csvHeaders(string $targetCurrency): array
    {
        return [
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
        ];
    }

    /**
     * @param  resource  $handle
     */
    private function writeCsv(
        $handle,
        int $teamId,
        string $operation,
        Carbon $from,
        Carbon $to,
        string $targetCurrency,
        string $documentScope,
    ): void {
        fputcsv($handle, $this->csvHeaders($targetCurrency));

        $totals = [
            'subtotal' => 0.0,
            'tax' => 0.0,
            'total' => 0.0,
            'rows' => 0,
        ];

        $query = $this->scopedInvoices($teamId, $operation, $from, $to, $documentScope);

        $query
            ->orderBy('number')
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
    }

    /**
     * @return array{
     *     headers: list<string>,
     *     books: array<string, array{rows: list<list<string>>, invoice_ids: list<int|null>, totals: list<string>}>
     * }
     */
    public function presentationBooks(int $teamId, Carbon $from, Carbon $to, string $targetCurrency): array
    {
        $books = [
            'sell' => ['operation' => 'sell', 'documentScope' => 'invoices'],
            'buy' => ['operation' => 'buy', 'documentScope' => 'invoices'],
            'sell_credit_notes' => ['operation' => 'sell', 'documentScope' => 'credit_notes'],
            'buy_credit_notes' => ['operation' => 'buy', 'documentScope' => 'credit_notes'],
        ];

        $presentation = [];

        foreach ($books as $key => $book)
        {
            $rows = [];
            $invoiceIds = [];
            $totals = ['subtotal' => 0.0, 'tax' => 0.0, 'total' => 0.0, 'rows' => 0];

            $invoices = $this->scopedInvoices($teamId, $book['operation'], $from, $to, $book['documentScope'])
                ->orderBy('number')
                ->orderBy('id')
                ->get();

            foreach ($invoices as $invoice)
            {
                [$row, $converted] = $this->rowForInvoice($invoice, $targetCurrency, $from);
                $rows[] = $row;
                $invoiceIds[] = $this->hasDownloadableDocument($invoice) ? $invoice->id : null;

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

            $presentation[$key] = [
                'rows' => $rows,
                'invoice_ids' => $invoiceIds,
                'totals' => [
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
                ],
            ];
        }

        return [
            'headers' => $this->csvHeaders($targetCurrency),
            'books' => $presentation,
        ];
    }

    /**
     * @return Builder<Invoice>
     */
    private function scopedInvoices(
        int $teamId,
        string $operation,
        Carbon $from,
        Carbon $to,
        string $documentScope,
    ): Builder {
        $query = $this->vatReportingService
            ->invoicesForPeriod($teamId, $operation, $from, $to)
            ->with([
                'items',
                'currency',
                'enterprise.enterpriseBillingAddresses',
                'billingAddress',
                'stripeInvoiceSync',
                'payments' => function ($payments)
                {
                    $payments->withoutGlobalScopes()->select(['id', 'invoice_id', 'remarks']);
                },
            ]);

        if ($documentScope === 'credit_notes')
        {
            $query->where(function ($creditNotes)
            {
                $creditNotes->whereIn('status', [4, 6])
                    ->orWhere('type_id', 2)
                    ->orWhere('source_reference_id', 'like', 'cn_%');
            });
        } else
        {
            $query->whereNotIn('status', [4, 6])
                ->where('type_id', '!=', 2)
                ->where(function ($regular)
                {
                    $regular->whereNull('source_reference_id')
                        ->orWhere('source_reference_id', 'not like', 'cn_%');
                });
        }

        return $query;
    }

    private function renderCsv(
        int $teamId,
        string $operation,
        Carbon $from,
        Carbon $to,
        string $targetCurrency,
        string $documentScope,
    ): string {
        $handle = fopen('php://temp', 'r+');
        $this->writeCsv($handle, $teamId, $operation, $from, $to, $targetCurrency, $documentScope);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @return array{0: list<string>, 1: array{subtotal: ?float, tax: ?float, total: ?float}}
     */
    private function rowForInvoice(Invoice $invoice, string $targetCurrency, Carbon $fallbackDate): array
    {
        $currency = strtoupper((string) $invoice->currency_code);
        // Fiscal conversion uses the invoice issue date (same as Stripe Hacienda CSV).
        $invoiceDate = $invoice->date ? Carbon::parse($invoice->date) : $fallbackDate;
        $sign = $invoice->isCreditNote() ? -1.0 : 1.0;
        $total = $sign * abs((float) $invoice->total_amount);
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
            number_format($subtotal, 2, ',', '.'),
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
        $own = $this->taxIdFromInvoice($invoice);
        if ($own !== '')
        {
            return $own;
        }

        if ($invoice->isCreditNote())
        {
            $original = $invoice->originalInvoice();
            if ($original instanceof Invoice)
            {
                $original->loadMissing(['billingAddress', 'stripeInvoiceSync']);
                $fromOriginal = $this->taxIdFromInvoice($original);
                if ($fromOriginal !== '')
                {
                    return $fromOriginal;
                }
            }
        }

        return $this->taxIdFromEnterprise($invoice);
    }

    private function taxIdFromInvoice(Invoice $invoice): string
    {
        $billingTaxId = trim((string) ($invoice->billingAddress?->identification_number ?? ''));
        if ($billingTaxId !== '')
        {
            return $this->cleanTaxId($billingTaxId);
        }

        $sync = $invoice->stripeInvoiceSync;
        $syncTaxId = trim((string) ($sync?->customer_tax_id ?? ''));
        if ($syncTaxId === '' && $sync instanceof InvoiceSync)
        {
            $syncTaxId = trim((string) data_get($sync->raw_payload, 'customer_tax_ids.0.value', ''));
        }

        return $this->cleanTaxId($syncTaxId);
    }

    private function taxIdFromEnterprise(Invoice $invoice): string
    {
        $addresses = $invoice->enterprise?->enterpriseBillingAddresses;
        if ($addresses === null || $addresses->isEmpty())
        {
            return '';
        }

        $withNumber = $addresses->filter(
            fn ($address): bool => trim((string) $address->identification_number) !== '',
        );
        $active = $withNumber->first(fn ($address): bool => (int) $address->status === 1);
        $address = $active ?? $withNumber->first();

        return $address ? $this->cleanTaxId((string) $address->identification_number) : '';
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

    public function downloadPublicDocument(Invoice $invoice): StreamedResponse
    {
        $relativePath = $this->localDocumentRelativePath($invoice);
        $url = null;
        if ($relativePath === null)
        {
            $url = $this->stripeInvoicePdfRefresher->freshPdfUrl($invoice) ?? $this->storedFileUrl($invoice);
            $relativePath = is_string($url) ? $this->relativePathFromStorageUrl($url) : null;
        }

        if (is_string($relativePath))
        {
            $absolutePath = Storage::disk('public')->path($relativePath);
            $body = Storage::disk('public')->get($relativePath);
            if (! is_string($body) || $body === '')
            {
                abort(404);
            }

            $mime = mime_content_type($absolutePath);
            $contentType = is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';

            return $this->streamDocumentResponse($invoice, $body, basename($relativePath), $contentType);
        }

        if (! is_string($url) || ! $this->documentUrlIsFetchable($url) || $this->isHostedInvoicePage($url))
        {
            abort(404);
        }

        $response = Http::timeout(20)
            ->withOptions([
                'allow_redirects' => [
                    'max' => 3,
                    'protocols' => ['http', 'https'],
                ],
            ])
            ->get($url);

        $body = $response->body();
        $contentType = strtolower((string) $response->header('Content-Type'));
        $isPdf = $response->successful()
            && (str_contains($contentType, 'pdf') || str_starts_with($body, '%PDF'));

        if (! $isPdf)
        {
            abort(404);
        }

        return $this->streamDocumentResponse(
            $invoice,
            $body,
            $this->upstreamFilename($response, $url, $invoice),
            'application/pdf',
        );
    }

    private function streamDocumentResponse(Invoice $invoice, string $body, string $upstreamFilename, string $contentType): StreamedResponse
    {
        $filename = $this->filenameWithIssuerPrefix($invoice, $upstreamFilename);

        return response()->streamDownload(function () use ($body): void
        {
            echo $body;
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }

    private function documentUrlIsFetchable(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true))
        {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP))
        {
            return false;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($scheme === 'http' && $host !== $appHost)
        {
            return false;
        }

        return ! in_array($host, ['localhost', 'metadata.google.internal'], true) || $host === $appHost;
    }

    private function upstreamFilename(HttpClientResponse $response, string $url, Invoice $invoice): string
    {
        $header = (string) $response->header('Content-Disposition');
        $name = '';

        if (preg_match("/filename\\*=UTF-8''([^;]+)/i", $header, $matches) === 1)
        {
            $name = rawurldecode($matches[1]);
        } elseif (preg_match('/filename="?([^";]+)"?/i', $header, $matches) === 1)
        {
            $name = $matches[1];
        }

        $name = trim(str_replace(['\\', '/'], '', $name));
        if ($name === '')
        {
            $name = basename((string) parse_url($url, PHP_URL_PATH));
        }

        if ($name === '' || ! str_contains($name, '.') || strtolower($name) === 'pdf')
        {
            $number = trim((string) $invoice->number);
            $name = ($number !== '' ? $number : 'factura').'.pdf';
        }

        return $name;
    }

    private function filenameWithIssuerPrefix(Invoice $invoice, string $filename): string
    {
        $issuer = $this->sanitizeFilenamePart($this->resolveIssuerName($invoice));
        $counterparty = $this->sanitizeFilenamePart($this->resolveEnterpriseName($invoice));
        $filename = trim(str_replace(['\\', '/'], '', $filename));
        $client = $counterparty !== '' && strcasecmp($counterparty, $issuer) !== 0 ? $counterparty : '';

        if ($client === '')
        {
            $name = $issuer !== '' ? $issuer.' - '.$filename : $filename;

            return Str::limit($name, 180, '');
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $invoiceLabel = $this->sanitizeFilenamePart((string) pathinfo($filename, PATHINFO_FILENAME));
        if ($invoiceLabel === '')
        {
            $invoiceLabel = $this->sanitizeFilenamePart(trim((string) $invoice->number));
        }

        $parts = array_values(array_filter(
            [$issuer, $invoiceLabel, $client],
            fn (string $part): bool => $part !== '',
        ));
        $name = implode(' - ', $parts);
        if ($extension !== '')
        {
            $name .= '.'.$extension;
        }

        return Str::limit($name, 180, '');
    }

    private function resolveIssuerName(Invoice $invoice): string
    {
        if ((string) $invoice->operation === 'buy')
        {
            return $this->resolveEnterpriseName($invoice);
        }

        $invoice->loadMissing('team');

        return trim((string) ($invoice->team?->name ?? ''));
    }

    private function sanitizeFilenamePart(string $value): string
    {
        $value = trim((string) preg_replace('/[\\\\\\/:*?"<>|]+/', ' ', $value));

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function resolveLink(Invoice $invoice): string
    {
        if (! $this->hasDownloadableDocument($invoice))
        {
            return '';
        }

        return $this->permanentDocumentUrl($invoice)
            ?? $this->storedFileUrl($invoice)
            ?? '';
    }

    private function hasDownloadableDocument(Invoice $invoice): bool
    {
        $externalId = trim((string) $invoice->source_reference_id);
        if (str_starts_with($externalId, 'in_') || str_starts_with($externalId, 'cn_'))
        {
            return true;
        }

        if ($this->localDocumentRelativePath($invoice) !== null)
        {
            return true;
        }

        return $this->storedFileUrl($invoice) !== null;
    }

    private function localDocumentRelativePath(Invoice $invoice): ?string
    {
        return app(ManualInvoiceDocumentService::class)->storedRelativePath($invoice);
    }

    private function relativePathFromStorageUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_contains($path, '/storage/'))
        {
            return null;
        }

        $relative = ltrim((string) strstr($path, '/storage/'), '/');
        $relative = substr($relative, strlen('storage/'));
        if ($relative === '' || str_contains($relative, '..') || ! Storage::disk('public')->exists($relative))
        {
            return null;
        }

        return $relative;
    }

    private function permanentDocumentUrl(Invoice $invoice): ?string
    {
        if (! $this->hasDownloadableDocument($invoice))
        {
            return null;
        }

        $teamId = (int) $invoice->team_id;
        if (! array_key_exists($teamId, $this->shareHashes))
        {
            $team = Team::query()->find($teamId);
            $this->shareHashes[$teamId] = $team instanceof Team ? $team->haciendaShareHash() : null;
        }

        $hash = $this->shareHashes[$teamId];
        if (! is_string($hash) || $hash === '')
        {
            return null;
        }

        return route('hacienda.public.file', [
            'hash' => $hash,
            'invoice' => $invoice->id,
        ]);
    }

    private function isHostedInvoicePage(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST)) === 'invoice.stripe.com';
    }

    private function storedFileUrl(Invoice $invoice): ?string
    {
        $pdfUrl = $invoice->stripeInvoicePdfUrl();
        if (is_string($pdfUrl) && $pdfUrl !== '' && ! $this->isHostedInvoicePage($pdfUrl))
        {
            return $pdfUrl;
        }

        $payments = $invoice->relationLoaded('payments')
            ? $invoice->payments
            : $invoice->payments()->withoutGlobalScopes()->get(['id', 'invoice_id', 'remarks']);

        foreach ($payments as $payment)
        {
            if (preg_match('/Documento:\s*(\S+)/', (string) $payment->remarks, $matches) === 1)
            {
                return $matches[1];
            }
        }

        return null;
    }
}
