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
use Illuminate\Support\Str;
use InvalidArgumentException;

class ManualInvoiceDocumentService
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{is_draft: bool}
     */
    public function store(
        array $validated,
        int $teamId,
        ?UploadedFile $documentFile,
        string $operation,
    ): array {
        if (! in_array($operation, ['buy', 'sell'], true))
        {
            throw new InvalidArgumentException('The invoice operation must be buy or sell.');
        }

        $lineSummaries = $this->buildLineSummaries($validated['lines']);
        $invoiceTotal = round(max((float) collect($lineSummaries)->sum('allocated_total'), 0.01), 2);
        $paymentEntries = $this->resolvePaymentEntries($validated['payments'] ?? []);
        $paymentsTotal = round((float) collect($paymentEntries)->sum('amount'), 2);
        $invoiceBalance = round(max($invoiceTotal - $paymentsTotal, 0), 2);
        $currencyCode = $this->resolveCurrencyCode($validated);
        $currencyId = $this->resolveCurrencyId($validated);
        $invoiceTypeId = $this->resolveInvoiceTypeId();
        $categoryId = isset($validated['expense_category_id'])
            ? (int) $validated['expense_category_id']
            : null;
        $categoryName = $this->resolveExpenseCategoryName($validated);
        $isDraft = ($validated['submit_action'] ?? 'save') === 'draft';

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
            $operation,
        ): void {
            $invoice = Invoice::withoutGlobalScopes()->create([
                'team_id' => $teamId,
                'enterprise_id' => (int) $validated['enterprise_id'],
                'billing_id' => null,
                'type_id' => $invoiceTypeId,
                'operation' => $operation,
                'number' => $this->composeInvoiceNumber($validated, $operation),
                'date' => $validated['date'],
                'due_date' => $validated['due_date'] ?? collect($paymentEntries)->pluck('payment_date')->filter()->last() ?? $validated['date'],
                'gross_amount' => $invoiceTotal,
                'discount' => 0,
                'total_amount' => $invoiceTotal,
                'balance' => $invoiceBalance,
                'currency_id' => $currencyId,
                'status' => 2,
                'source_provider' => 'manual',
            ]);

            $storedDocumentPath = $this->storeDocumentFile(
                $documentFile,
                $teamId,
                (int) $invoice->id,
                $operation,
            );

            $this->createInvoiceItems($invoice, $lineSummaries, $categoryId);

            foreach ($paymentEntries as $paymentEntry)
            {
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
                    'remarks' => $this->buildRemarks(
                        $validated,
                        (float) $paymentEntry['amount'],
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

        return ['is_draft' => $isDraft];
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

            $resolved[] = array_merge($payment, [
                'amount' => round((float) $payment['amount'], 2),
            ]);
        }

        return $resolved;
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

        $fileName = $normalizedName.'-'.now()->format('YmdHis').'.'.$extension;
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
