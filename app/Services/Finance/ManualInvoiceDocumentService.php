<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceType;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualInvoiceDocumentService
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{is_draft: bool, invoice: Invoice}
     */
    public function store(
        array $validated,
        int $teamId,
        ?UploadedFile $documentFile,
        string $operation,
        ?string $pendingDocumentToken = null,
    ): array {
        if (! in_array($operation, ['buy', 'sell'], true))
        {
            throw new InvalidArgumentException('The invoice operation must be buy or sell.');
        }

        $lineSummaries = $this->buildLineSummaries($validated['lines']);
        $invoiceTotal = round(max((float) collect($lineSummaries)->sum('allocated_total'), 0.01), 2);
        $paymentEntries = $this->resolvePaymentEntries($validated['payments'] ?? []);
        $currencyCode = $this->resolveCurrencyCode($validated);
        $currencyId = $this->resolveCurrencyId($validated);
        $paymentsTotal = round((float) collect($paymentEntries)->sum(
            fn (array $paymentEntry): float => $this->paymentAppliedToInvoice($paymentEntry, $currencyId),
        ), 2);
        $invoiceBalance = round(max($invoiceTotal - $paymentsTotal, 0), 2);
        $isCreditNote = ($validated['document_type'] ?? '') === 'credit_note';
        $invoiceTypeId = $isCreditNote ? 2 : $this->resolveInvoiceTypeId();
        $categoryId = isset($validated['expense_category_id'])
            ? (int) $validated['expense_category_id']
            : null;
        $categoryName = $this->resolveExpenseCategoryName($validated);
        $isDraft = ($validated['submit_action'] ?? 'save') === 'draft';
        $invoice = null;

        DB::transaction(function () use (
            $validated,
            $teamId,
            $invoiceTypeId,
            $invoiceTotal,
            $invoiceBalance,
            $currencyId,
            $currencyCode,
            $lineSummaries,
            $categoryId,
            $categoryName,
            $documentFile,
            $paymentEntries,
            $isDraft,
            $isCreditNote,
            $operation,
            $pendingDocumentToken,
            &$invoice,
        ): void {
            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'enterprise_id' => (int) $validated['enterprise_id'],
                'billing_id' => null,
                'type_id' => $isCreditNote ? 2 : $invoiceTypeId,
                'operation' => $operation,
                'number' => $this->composeInvoiceNumber($validated, $operation),
                'date' => $validated['date'],
                'due_date' => $validated['due_date'] ?? collect($paymentEntries)->pluck('payment_date')->filter()->last() ?? $validated['date'],
                'gross_amount' => $invoiceTotal,
                'discount' => 0,
                'total_amount' => $invoiceTotal,
                'balance' => $invoiceBalance,
                'currency_id' => $currencyId,
                'status' => $isCreditNote ? 4 : 2,
                'source_provider' => 'manual',
            ]);

            $storedDocumentPath = $this->storeDocumentFile(
                $documentFile,
                $teamId,
                (int) $invoice->id,
                $operation,
            );

            if (! is_string($storedDocumentPath) || $storedDocumentPath === '')
            {
                $storedDocumentPath = $this->adoptPendingDocument(
                    $teamId,
                    (int) $invoice->id,
                    $operation,
                    $pendingDocumentToken,
                );
            } elseif (is_string($pendingDocumentToken) && $pendingDocumentToken !== '')
            {
                $this->deletePendingDocument($teamId, $pendingDocumentToken);
            }

            $this->createInvoiceItems($invoice, $lineSummaries, $categoryId);

            foreach ($paymentEntries as $paymentEntry)
            {
                $appliedAmount = $this->paymentAppliedToInvoice($paymentEntry, $currencyId);

                Payment::query()->create([
                    'team_id' => $teamId,
                    'enterprise_id' => (int) $validated['enterprise_id'],
                    'transaction_type' => $operation === 'sell'
                        ? TransactionType::INCOME
                        : TransactionType::EXPENSE,
                    'date' => $paymentEntry['payment_date'],
                    'invoice_id' => $invoice->id,
                    'account_id' => (int) $paymentEntry['account_id'],
                    'type_id' => (int) $paymentEntry['type_id'],
                    'amount' => (float) $paymentEntry['amount'],
                    'document_amount' => $paymentEntry['document_amount'] ?? null,
                    'exchange_rate' => $paymentEntry['exchange_rate'] ?? null,
                    'remarks' => $this->buildRemarks(
                        $validated,
                        $appliedAmount,
                        $currencyCode,
                        $lineSummaries,
                        $categoryName,
                        $storedDocumentPath,
                        $paymentEntries,
                        $operation,
                    ),
                    'status' => $isDraft ? 1 : (int) $paymentEntry['status'],
                    'source_provider' => 'manual',
                ]);
            }
        });

        return [
            'is_draft' => $isDraft,
            'invoice' => $invoice,
        ];
    }

    /**
     * Update a manual buy/sell invoice from validated expense form data.
     *
     * Payments are synced by id: existing ids are updated, rows without a known id are
     * created and payments missing from the payload are deleted.
     *
     * @param  array<string, mixed>  $validated
     * @return array{invoice: Invoice}
     */
    public function update(
        Invoice $invoice,
        array $validated,
        ?UploadedFile $documentFile = null,
        ?string $pendingDocumentToken = null,
    ): array {
        $teamId = (int) $invoice->team_id;
        $operation = (string) $invoice->operation === 'sell' ? 'sell' : 'buy';
        $lineSummaries = $this->buildLineSummaries($validated['lines']);
        $invoiceTotal = round(max((float) collect($lineSummaries)->sum('allocated_total'), 0.01), 2);
        $paymentEntries = $this->resolvePaymentEntries($validated['payments'] ?? []);
        $currencyCode = $this->resolveCurrencyCode($validated);
        $currencyId = $this->resolveCurrencyId($validated);
        $paymentsTotal = round((float) collect($paymentEntries)->sum(
            fn (array $paymentEntry): float => $this->paymentAppliedToInvoice($paymentEntry, $currencyId),
        ), 2);
        $invoiceBalance = round(max($invoiceTotal - $paymentsTotal, 0), 2);
        $isCreditNote = ($validated['document_type'] ?? '') === 'credit_note';
        $wasCreditNote = (int) $invoice->type_id === 2;
        $categoryId = isset($validated['expense_category_id'])
            ? (int) $validated['expense_category_id']
            : null;
        $categoryName = $this->resolveExpenseCategoryName($validated);
        $isDraft = ($validated['submit_action'] ?? 'save') === 'draft';

        DB::transaction(function () use (
            $invoice,
            $validated,
            $teamId,
            $operation,
            $lineSummaries,
            $invoiceTotal,
            $invoiceBalance,
            $paymentEntries,
            $currencyId,
            $currencyCode,
            $isCreditNote,
            $wasCreditNote,
            $categoryId,
            $categoryName,
            $isDraft,
            $documentFile,
            $pendingDocumentToken,
        ): void {
            $storedDocumentPath = null;

            if ($documentFile instanceof UploadedFile)
            {
                $storedDocumentPath = $this->attachDocument($invoice, $documentFile);

                if (is_string($pendingDocumentToken) && $pendingDocumentToken !== '')
                {
                    $this->deletePendingDocument($teamId, $pendingDocumentToken);
                }
            } else
            {
                $adoptedPath = $this->adoptPendingDocument(
                    $teamId,
                    (int) $invoice->id,
                    $operation,
                    $pendingDocumentToken,
                );

                if (is_string($adoptedPath) && $adoptedPath !== '')
                {
                    $this->deleteSiblingDocuments($adoptedPath);
                    $storedDocumentPath = $adoptedPath;
                }
            }

            $storedDocumentPath ??= $this->storedRelativePath($invoice);

            $attributes = [
                'enterprise_id' => (int) $validated['enterprise_id'],
                'number' => filled($validated['document_number'] ?? null)
                    ? trim((string) $validated['document_number'])
                    : $invoice->number,
                'date' => $validated['date'],
                'due_date' => $validated['due_date'] ?? collect($paymentEntries)->pluck('payment_date')->filter()->last() ?? $validated['date'],
                'gross_amount' => $invoiceTotal,
                'total_amount' => $invoiceTotal,
                'balance' => $invoiceBalance,
                'currency_id' => $currencyId,
            ];

            if ($isCreditNote)
            {
                $attributes['type_id'] = 2;
                $attributes['status'] = 4;
            } elseif ($wasCreditNote)
            {
                $attributes['type_id'] = $this->resolveInvoiceTypeId();
                $attributes['status'] = 2;
            }

            $invoice->update($attributes);

            InvoiceItem::query()->where('invoice_id', $invoice->id)->delete();
            $this->createInvoiceItems($invoice, $lineSummaries, $categoryId);

            $existingPayments = Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->get()
                ->keyBy('id');
            $keptPaymentIds = [];

            foreach ($paymentEntries as $paymentEntry)
            {
                $appliedAmount = $this->paymentAppliedToInvoice($paymentEntry, $currencyId);
                $attributes = [
                    'enterprise_id' => (int) $validated['enterprise_id'],
                    'date' => $paymentEntry['payment_date'],
                    'account_id' => (int) $paymentEntry['account_id'],
                    'type_id' => (int) $paymentEntry['type_id'],
                    'amount' => (float) $paymentEntry['amount'],
                    'document_amount' => $paymentEntry['document_amount'] ?? null,
                    'exchange_rate' => $paymentEntry['exchange_rate'] ?? null,
                    'remarks' => $this->buildRemarks(
                        $validated,
                        $appliedAmount,
                        $currencyCode,
                        $lineSummaries,
                        $categoryName,
                        $storedDocumentPath,
                        $paymentEntries,
                        $operation,
                    ),
                    'status' => $isDraft ? 1 : (int) $paymentEntry['status'],
                ];

                $paymentId = filled($paymentEntry['id'] ?? null) ? (int) $paymentEntry['id'] : null;
                $existingPayment = $paymentId !== null ? $existingPayments->get($paymentId) : null;

                if ($existingPayment instanceof Payment)
                {
                    $existingPayment->update($attributes);
                    $keptPaymentIds[] = (int) $existingPayment->id;

                    continue;
                }

                Payment::query()->create(array_merge($attributes, [
                    'team_id' => $teamId,
                    'transaction_type' => $operation === 'sell'
                        ? TransactionType::INCOME
                        : TransactionType::EXPENSE,
                    'invoice_id' => $invoice->id,
                    'source_provider' => 'manual',
                ]));
            }

            $removedPaymentIds = $existingPayments->keys()
                ->map(fn ($id): int => (int) $id)
                ->diff($keptPaymentIds)
                ->values()
                ->all();

            if ($removedPaymentIds !== [])
            {
                Payment::withoutGlobalScopes()->whereIn('id', $removedPaymentIds)->delete();
            }
        });

        return ['invoice' => $invoice->refresh()];
    }

    /**
     * Download (or display inline) the stored supplier document of an invoice.
     */
    public function documentResponse(Invoice $invoice, bool $inline = false): StreamedResponse
    {
        $path = $this->storedRelativePath($invoice);

        if (! is_string($path) || $path === '')
        {
            abort(404);
        }

        if ($inline)
        {
            return Storage::disk('public')->response($path);
        }

        return Storage::disk('public')->download($path, basename($path));
    }

    /**
     * Metadata that is not stored in dedicated columns but can be recovered from payment remarks.
     *
     * @return array{document_type: string, tags: ?string, cash_criteria: bool, is_investment: bool, lines: array<int, array{base_amount: float, vat_percent: float, retention_percent: float, allocation_percent: float}>}
     */
    public function storedMetadata(Invoice $invoice): array
    {
        $remarks = (string) Payment::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('remarks')
            ->orderBy('id')
            ->value('remarks');

        $documentType = $invoice->isCreditNote() ? 'credit_note' : 'invoice';

        if (preg_match('/Tipo de documento: ([a-z_]+)/', $remarks, $matches) === 1
            && in_array($matches[1], ['invoice', 'credit_note', 'receipt', 'tax'], true))
        {
            $documentType = $matches[1];
        }

        $tags = preg_match('/Etiquetas: ([^|]+)/u', $remarks, $matches) === 1
            ? trim($matches[1])
            : null;

        $lines = [];
        $linePattern = '/Línea \d+: .*? \| Base: (-?[\d.]+) \| IVA: (-?[\d.]+)% \| Retención: (-?[\d.]+)% \| Imputa: (-?[\d.]+)%/u';

        if (preg_match_all($linePattern, $remarks, $matches, PREG_SET_ORDER) > 0)
        {
            foreach ($matches as $match)
            {
                $lines[] = [
                    'base_amount' => (float) $match[1],
                    'vat_percent' => (float) $match[2],
                    'retention_percent' => (float) $match[3],
                    'allocation_percent' => (float) $match[4],
                ];
            }
        }

        return [
            'document_type' => $documentType,
            'tags' => $tags !== '' ? $tags : null,
            'cash_criteria' => str_contains($remarks, 'Criterio de caja: sí'),
            'is_investment' => str_contains($remarks, 'Inversión: sí'),
            'lines' => $lines,
        ];
    }

    public function attachDocument(Invoice $invoice, UploadedFile $documentFile): string
    {
        $operation = (string) $invoice->operation === 'sell' ? 'sell' : 'buy';
        $storedPath = $this->storeDocumentFile(
            $documentFile,
            (int) $invoice->team_id,
            (int) $invoice->id,
            $operation,
        );

        if (! is_string($storedPath) || $storedPath === '')
        {
            throw new InvalidArgumentException('The document could not be stored.');
        }

        $this->deleteSiblingDocuments($storedPath);

        return $storedPath;
    }

    public function storedRelativePath(Invoice $invoice): ?string
    {
        $teamHash = Team::generateTeamHash((int) $invoice->team_id);
        $invoiceHash = substr(md5('invoice_salt_'.$invoice->id.'_'.config('app.key')), 0, 8);
        $root = (string) $invoice->operation === 'sell' ? 'invoices' : 'expenses';
        $directory = $root.'/'.$teamHash.'/'.$invoiceHash;
        $files = Storage::disk('public')->exists($directory)
            ? Storage::disk('public')->files($directory)
            : [];
        $file = $files[0] ?? null;

        return is_string($file) && $file !== '' ? $file : null;
    }

    private function deleteSiblingDocuments(string $storedPath): void
    {
        $directory = dirname($storedPath);
        if ($directory === '' || $directory === '.')
        {
            return;
        }

        foreach (Storage::disk('public')->files($directory) as $file)
        {
            if ($file !== $storedPath)
            {
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function buildLineSummaries(array $lines): array
    {
        return collect($lines)->values()->map(function (array $line): array
        {
            $baseAmount = round((float) ($line['base_amount'] ?? 0), 2);
            $vatPercent = (float) ($line['vat_percent'] ?? 0);
            $retentionPercent = (float) ($line['retention_percent'] ?? 0);
            $allocationPercent = (float) ($line['allocation_percent'] ?? 100);
            $vatAmount = round($baseAmount * ($vatPercent / 100), 2);
            $retentionAmount = round($baseAmount * ($retentionPercent / 100), 2);
            $lineTotal = round($baseAmount + $vatAmount - $retentionAmount, 2);

            return [
                'concept' => (string) ($line['concept'] ?? ''),
                'category_id' => ! empty($line['category_id']) ? (int) $line['category_id'] : null,
                'base_amount' => $baseAmount,
                'vat_percent' => $vatPercent,
                'retention_percent' => $retentionPercent,
                'allocation_percent' => $allocationPercent,
                'vat_amount' => $vatAmount,
                'retention_amount' => $retentionAmount,
                'line_total' => $lineTotal,
                'allocated_total' => round($lineTotal * ($allocationPercent / 100), 2),
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<int, array<string, mixed>>
     */
    private function resolvePaymentEntries(array $payments): array
    {
        $resolved = [];

        foreach ($payments as $payment)
        {
            if (! filled($payment['amount'] ?? null))
            {
                continue;
            }

            $documentAmount = filled($payment['document_amount'] ?? null)
                ? round((float) $payment['document_amount'], 2)
                : null;
            $exchangeRate = filled($payment['exchange_rate'] ?? null)
                ? round((float) $payment['exchange_rate'], 6)
                : null;

            $resolved[] = array_merge($payment, [
                'amount' => round((float) $payment['amount'], 2),
                'document_amount' => $documentAmount,
                'exchange_rate' => $exchangeRate,
            ]);
        }

        return $resolved;
    }

    /**
     * Amount applied against the invoice total (document currency).
     * Cross-currency payments without document_amount do not reduce the balance yet.
     *
     * @param  array<string, mixed>  $paymentEntry
     */
    private function paymentAppliedToInvoice(array $paymentEntry, ?int $invoiceCurrencyId): float
    {
        if (filled($paymentEntry['document_amount'] ?? null))
        {
            return round((float) $paymentEntry['document_amount'], 2);
        }

        $accountId = (int) ($paymentEntry['account_id'] ?? 0);
        $account = $accountId > 0
            ? PaymentAccount::withoutGlobalScopes()->find($accountId)
            : null;

        if (
            $invoiceCurrencyId
            && $account instanceof PaymentAccount
            && $account->currency_id !== null
            && (int) $account->currency_id !== $invoiceCurrencyId
        ) {
            return 0.0;
        }

        return round((float) ($paymentEntry['amount'] ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function composeInvoiceNumber(array $validated, string $operation): string
    {
        if (filled($validated['document_number'] ?? null))
        {
            return trim((string) $validated['document_number']);
        }

        $prefix = $operation === 'sell' ? 'FV-' : 'GC-';

        return $prefix.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineSummaries
     */
    private function createInvoiceItems(Invoice $invoice, array $lineSummaries, ?int $fallbackCategoryId): void
    {
        foreach ($lineSummaries as $lineSummary)
        {
            $allocationFactor = (float) ($lineSummary['allocation_percent'] ?? 100) / 100;
            $unitPrice = round((float) ($lineSummary['base_amount'] ?? 0) * $allocationFactor, 2);
            $categoryId = ! empty($lineSummary['category_id'])
                ? (int) $lineSummary['category_id']
                : $fallbackCategoryId;

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'category_id' => $categoryId,
                'description' => (string) $lineSummary['concept'],
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'discount' => 0,
                'tax_percentage' => round((float) ($lineSummary['vat_percent'] ?? 0), 2),
            ]);
        }
    }

    public function storePendingDocument(UploadedFile $documentFile, int $teamId): string
    {
        $token = Str::random(40);
        $teamHash = Team::generateTeamHash($teamId);
        $fileName = $this->documentFileName($documentFile, false);
        $documentFile->storeAs(
            "expenses-pending/{$teamHash}/{$token}",
            $fileName,
            'public',
        );

        return $token;
    }

    private function adoptPendingDocument(
        int $teamId,
        int $invoiceId,
        string $operation,
        ?string $token,
    ): ?string {
        $directory = $this->pendingDocumentDirectory($teamId, $token);

        if ($directory === null)
        {
            return null;
        }

        $source = collect(Storage::disk('public')->files($directory))
            ->first(fn (string $path): bool => ! str_contains($path, '..'));

        if (! is_string($source) || $source === '')
        {
            return null;
        }

        $teamHash = Team::generateTeamHash($teamId);
        $invoiceHash = substr(md5('invoice_salt_'.$invoiceId.'_'.config('app.key')), 0, 8);
        $rootDirectory = $operation === 'sell' ? 'invoices' : 'expenses';
        $target = "{$rootDirectory}/{$teamHash}/{$invoiceHash}/".basename($source);

        Storage::disk('public')->move($source, $target);
        Storage::disk('public')->deleteDirectory($directory);

        return $target;
    }

    private function deletePendingDocument(int $teamId, ?string $token): void
    {
        $directory = $this->pendingDocumentDirectory($teamId, $token);

        if ($directory === null)
        {
            return;
        }

        Storage::disk('public')->deleteDirectory($directory);
    }

    private function pendingDocumentDirectory(int $teamId, ?string $token): ?string
    {
        if (! is_string($token) || preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1)
        {
            return null;
        }

        return 'expenses-pending/'.Team::generateTeamHash($teamId).'/'.$token;
    }

    private function documentFileName(UploadedFile $documentFile, bool $withTimestamp): string
    {
        $originalName = pathinfo((string) $documentFile->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower((string) $documentFile->getClientOriginalExtension());
        $normalizedName = Str::slug(Str::ascii($originalName));

        if ($normalizedName === '')
        {
            $normalizedName = 'documento';
        }

        if ($extension === '')
        {
            $extension = 'pdf';
        }

        if (! $withTimestamp)
        {
            return $normalizedName.'.'.$extension;
        }

        return $normalizedName.'-'.now()->format('YmdHis').'.'.$extension;
    }

    private function storeDocumentFile(
        ?UploadedFile $documentFile,
        int $teamId,
        int $invoiceId,
        string $operation,
    ): ?string {
        if (! $documentFile instanceof UploadedFile)
        {
            return null;
        }

        $teamHash = Team::generateTeamHash($teamId);
        $invoiceHash = substr(md5('invoice_salt_'.$invoiceId.'_'.config('app.key')), 0, 8);
        $fileName = $this->documentFileName($documentFile, true);
        $rootDirectory = $operation === 'sell' ? 'invoices' : 'expenses';

        return $documentFile->storeAs(
            "{$rootDirectory}/{$teamHash}/{$invoiceHash}",
            $fileName,
            'public',
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCurrencyCode(array $validated): string
    {
        if (! empty($validated['currency_id']))
        {
            $selectedCurrencyCode = Currency::query()
                ->whereKey((int) $validated['currency_id'])
                ->value('code');

            if (filled($selectedCurrencyCode))
            {
                return strtoupper((string) $selectedCurrencyCode);
            }
        }

        $accountId = $this->resolvePaymentAccountId($validated);
        if ($accountId === null)
        {
            return 'EUR';
        }

        $accountCurrencyCode = PaymentAccount::query()
            ->with('currency')
            ->whereKey($accountId)
            ->first()
            ?->currency
            ?->code;

        return filled($accountCurrencyCode)
            ? strtoupper((string) $accountCurrencyCode)
            : 'EUR';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCurrencyId(array $validated): ?int
    {
        if (! empty($validated['currency_id']))
        {
            return (int) $validated['currency_id'];
        }

        $accountId = $this->resolvePaymentAccountId($validated);
        if ($accountId === null)
        {
            return null;
        }

        $currencyId = PaymentAccount::query()->whereKey($accountId)->value('currency_id');

        return $currencyId !== null ? (int) $currencyId : null;
    }

    private function resolveInvoiceTypeId(): int
    {
        return (int) (InvoiceType::query()->orderBy('id')->value('id') ?? 1);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveExpenseCategoryName(array $validated): ?string
    {
        if (! empty($validated['expense_category_id']))
        {
            $categoryName = Category::query()
                ->whereKey((int) $validated['expense_category_id'])
                ->value('name');

            if (filled($categoryName))
            {
                return (string) $categoryName;
            }
        }

        return filled($validated['expense_category'] ?? null)
            ? (string) $validated['expense_category']
            : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolvePaymentAccountId(array $validated): ?int
    {
        $payments = $validated['payments'] ?? [];

        if (! is_array($payments))
        {
            return null;
        }

        foreach ($payments as $payment)
        {
            if (is_array($payment) && filled($payment['account_id'] ?? null))
            {
                return (int) $payment['account_id'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, array<string, mixed>>  $lineSummaries
     * @param  array<int, array<string, mixed>>  $paymentEntries
     */
    private function buildRemarks(
        array $validated,
        float $amount,
        string $currencyCode,
        array $lineSummaries,
        ?string $categoryName,
        ?string $storedDocumentPath,
        array $paymentEntries,
        string $operation,
    ): ?string {
        $lineRemarks = collect($lineSummaries)->map(function (array $line, int $index): string
        {
            return sprintf(
                'Línea %d: %s | Base: %.2f | IVA: %.2f%% | Retención: %.2f%% | Imputa: %.2f%% | Total: %.2f',
                $index + 1,
                (string) $line['concept'],
                (float) $line['base_amount'],
                (float) $line['vat_percent'],
                (float) $line['retention_percent'],
                (float) $line['allocation_percent'],
                (float) $line['allocated_total'],
            );
        })->implode(' || ');

        $categoryLabel = $operation === 'sell' ? 'Categoría' : 'Tipo de gasto';
        $remarks = array_filter([
            'Tipo de documento: '.(string) $validated['document_type'],
            filled($validated['document_number'] ?? null)
                ? 'Número de documento: '.(string) $validated['document_number']
                : null,
            filled($validated['due_date'] ?? null)
                ? 'Fecha de vencimiento: '.(string) $validated['due_date']
                : null,
            filled($categoryName) ? $categoryLabel.': '.$categoryName : null,
            filled($storedDocumentPath)
                ? 'Documento: '.asset('storage/'.ltrim((string) $storedDocumentPath, '/'))
                : null,
            $lineRemarks,
            $paymentEntries !== []
                ? 'Pagos: '.collect($paymentEntries)->map(function (array $payment): string
                {
                    return number_format((float) $payment['amount'], 2, '.', '').' ('.(string) $payment['payment_date'].')';
                })->implode(', ')
                : null,
            'Moneda: '.$currencyCode,
            'Total final: '.number_format($amount, 2, '.', '').' '.$currencyCode,
            ! empty($validated['cash_criteria']) ? 'Criterio de caja: sí' : null,
            ! empty($validated['is_investment']) ? 'Inversión: sí' : null,
            filled($validated['tags'] ?? null) ? 'Etiquetas: '.(string) $validated['tags'] : null,
            filled($validated['remarks'] ?? null) ? trim((string) $validated['remarks']) : null,
        ]);

        return $remarks === [] ? null : implode(' | ', $remarks);
    }
}
