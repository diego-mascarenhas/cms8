<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BankStatementReconcileService
{
    public const BANK_TRANSFER_TYPE_ID = 2;

    public function __construct(
        private readonly PaymentAccountStatementUploadService $statementUploadService,
    ) {}

    public function dismiss(BankStatementLine $line): BankStatementLine
    {
        $line->markDismissed();
        $line->forceFill(['match_status' => 'dismissed'])->save();

        return $line->fresh() ?? $line;
    }

    public function undismiss(BankStatementLine $line): BankStatementLine
    {
        $line->forceFill([
            'reconcile_dismissed_at' => null,
            'match_status' => $line->payment_id ? 'matched' : 'statement_only',
        ])->save();

        return $line->fresh() ?? $line;
    }

    /**
     * Register an internal transfer: outflow on $fromAccount, inflow on the statement account.
     * Links the destination payment to the bank line.
     *
     * @return array{outflow: Payment, inflow: Payment, line: BankStatementLine}
     */
    public function createTransfer(
        BankStatementLine $line,
        PaymentAccount $destinationAccount,
        PaymentAccount $fromAccount,
        ?string $remarks = null,
    ): array {
        if ((int) $destinationAccount->id === (int) $fromAccount->id)
        {
            throw new RuntimeException('Elige una cuenta de origen distinta a la de destino.');
        }

        if ((int) $destinationAccount->team_id !== (int) $fromAccount->team_id)
        {
            throw new RuntimeException('Las cuentas deben pertenecer al mismo equipo.');
        }

        $amount = abs((float) $line->amount);

        if ($amount <= 0)
        {
            throw new RuntimeException('El movimiento no tiene importe para traspasar.');
        }

        if ((float) $line->amount <= 0)
        {
            throw new RuntimeException('El traspaso aplica a ingresos del extracto (importes positivos).');
        }

        if ($line->payment_id)
        {
            throw new RuntimeException('Este movimiento ya está vinculado a un pago.');
        }

        $date = $line->occurred_at?->toDateString() ?? now()->toDateString();
        $transferKey = Str::uuid()->toString();
        $concept = trim((string) ($line->reference ?: $line->description ?: 'Traspaso'));
        $note = $remarks !== null && trim($remarks) !== ''
            ? trim($remarks)
            : __('Traspaso :from → :to · :concept', [
                'from' => $fromAccount->name,
                'to' => $destinationAccount->name,
                'concept' => $concept,
            ]);

        return DB::transaction(function () use (
            $line,
            $destinationAccount,
            $fromAccount,
            $amount,
            $date,
            $transferKey,
            $note,
        ): array {
            $outflow = Payment::withoutGlobalScopes()->create([
                'team_id' => (int) $fromAccount->team_id,
                'transaction_type' => TransactionType::EXPENSE,
                'date' => $date,
                'account_id' => (int) $fromAccount->id,
                'type_id' => self::BANK_TRANSFER_TYPE_ID,
                'amount' => $amount,
                'remarks' => $note,
                'status' => 2,
                'source_provider' => 'transfer',
                'source_reference_id' => 'transfer:'.$transferKey.':out',
            ]);

            $inflow = Payment::withoutGlobalScopes()->create([
                'team_id' => (int) $destinationAccount->team_id,
                'transaction_type' => TransactionType::INCOME,
                'date' => $date,
                'account_id' => (int) $destinationAccount->id,
                'type_id' => self::BANK_TRANSFER_TYPE_ID,
                'amount' => $amount,
                'remarks' => $note,
                'status' => 2,
                'source_provider' => 'transfer',
                'source_reference_id' => 'transfer:'.$transferKey.':in',
            ]);

            $line->forceFill([
                'payment_id' => $inflow->id,
                'match_status' => 'matched',
                'reconcile_dismissed_at' => null,
            ])->save();

            $statement = $line->statement;
            if ($statement instanceof BankStatement)
            {
                $validation = $this->statementUploadService->validateAgainstPayments($statement, $destinationAccount);
                $statement->forceFill([
                    'validation_summary' => array_merge($statement->validation_summary ?? [], $validation),
                ])->save();
            }

            return [
                'outflow' => $outflow,
                'inflow' => $inflow,
                'line' => $line->fresh(['payment']) ?? $line,
            ];
        });
    }

    /**
     * Attach an already-created payment (e.g. from "Crear gasto") to an open statement line.
     */
    public function attachExistingPayment(
        BankStatementLine $line,
        Payment $payment,
        PaymentAccount $account,
    ): BankStatementLine {
        if ($line->payment_id)
        {
            throw new RuntimeException('Este movimiento ya está vinculado a un pago.');
        }

        if ($line->isDismissed())
        {
            throw new RuntimeException('Restaurá el movimiento antes de asociarlo.');
        }

        $statement = $line->statement;

        if (! $statement instanceof BankStatement
            || (int) $statement->payment_account_id !== (int) $account->id)
        {
            throw new RuntimeException('El movimiento no pertenece a esta cuenta.');
        }

        if ((int) $payment->account_id !== (int) $account->id)
        {
            throw new RuntimeException('El pago no está en la misma cuenta del extracto.');
        }

        if ((int) $payment->team_id !== (int) $account->team_id)
        {
            throw new RuntimeException('El pago no pertenece al mismo equipo.');
        }

        return DB::transaction(function () use ($line, $payment, $account, $statement): BankStatementLine
        {
            if (blank($payment->source_reference_id))
            {
                $payment->forceFill([
                    'source_provider' => filled($payment->source_provider) ? $payment->source_provider : 'manual',
                    'source_reference_id' => 'statement-line:'.$line->id,
                ])->save();
            }

            $line->forceFill([
                'payment_id' => $payment->id,
                'match_status' => 'matched',
                'reconcile_dismissed_at' => null,
            ])->save();

            $validation = $this->statementUploadService->validateAgainstPayments($statement, $account);
            $statement->forceFill([
                'validation_summary' => array_merge($statement->validation_summary ?? [], $validation),
            ])->save();

            return $line->fresh(['payment']) ?? $line;
        });
    }

    /**
     * Create a payment on an existing invoice and match the bank statement line to it.
     *
     * @return array{
     *     payment: Payment,
     *     payments: list<Payment>,
     *     invoice: Invoice,
     *     invoices: list<Invoice>,
     *     line: BankStatementLine
     * }
     */
    public function linkToInvoice(
        BankStatementLine $line,
        Invoice $invoice,
        PaymentAccount $account,
        ?int $paymentTypeId = null,
        ?string $remarks = null,
    ): array {
        return $this->linkToInvoices($line, [$invoice], $account, $paymentTypeId, $remarks);
    }

    /**
     * Create payments on one or more invoices that together cover the bank line amount.
     *
     * Single invoice keeps FX behaviour (full bank amount / implied rate).
     * Multiple invoices require the same currency and allocate each invoice balance
     * until the bank amount is covered.
     *
     * @param  list<Invoice>  $invoices
     * @return array{
     *     payment: Payment,
     *     payments: list<Payment>,
     *     invoice: Invoice,
     *     invoices: list<Invoice>,
     *     line: BankStatementLine
     * }
     */
    public function linkToInvoices(
        BankStatementLine $line,
        array $invoices,
        PaymentAccount $account,
        ?int $paymentTypeId = null,
        ?string $remarks = null,
    ): array {
        if ($line->payment_id)
        {
            throw new RuntimeException('Este movimiento ya está vinculado a un pago.');
        }

        if ($line->isDismissed())
        {
            throw new RuntimeException('Restaurá el movimiento antes de asociarlo a una factura.');
        }

        $invoices = array_values(array_filter(
            $invoices,
            fn ($invoice): bool => $invoice instanceof Invoice,
        ));

        if ($invoices === [])
        {
            throw new RuntimeException('Elegí al menos una factura.');
        }

        $uniqueInvoices = [];
        foreach ($invoices as $invoice)
        {
            $uniqueInvoices[(int) $invoice->id] = $invoice;
        }
        $invoices = array_values($uniqueInvoices);

        $amount = abs(round((float) $line->amount, 2));

        if ($amount <= 0)
        {
            throw new RuntimeException('El movimiento no tiene importe para asociar.');
        }

        $isExpense = (float) $line->amount < 0;
        $expectedOperation = $isExpense ? 'buy' : 'sell';
        $typeId = $paymentTypeId ?: $this->defaultPaymentTypeId();

        if (! $typeId)
        {
            throw new RuntimeException('No hay formas de pago disponibles.');
        }

        $account->loadMissing('currency:id,code');
        $accountCurrencyId = $account->currency_id !== null ? (int) $account->currency_id : null;

        foreach ($invoices as $invoice)
        {
            if ((int) $invoice->team_id !== (int) $account->team_id)
            {
                throw new RuntimeException('La factura no pertenece al mismo equipo.');
            }

            if ((string) $invoice->operation !== $expectedOperation)
            {
                throw new RuntimeException($isExpense
                    ? 'Para un egreso del extracto elegí gastos (facturas de compra).'
                    : 'Para un ingreso del extracto elegí facturas de venta.');
            }

            if (round((float) $invoice->balance, 2) <= 0)
            {
                throw new RuntimeException('La factura '.$invoice->number.' no tiene saldo pendiente.');
            }
        }

        $allocations = $this->allocateInvoicePayments($invoices, $accountCurrencyId, $amount);

        $date = $line->occurred_at?->toDateString() ?? now()->toDateString();
        $concept = trim((string) ($line->reference ?: $line->description ?: ''));
        $note = $remarks !== null && trim($remarks) !== ''
            ? trim($remarks)
            : ($concept !== '' ? $concept : __('Pago desde extracto bancario'));

        return DB::transaction(function () use (
            $line,
            $account,
            $allocations,
            $isExpense,
            $typeId,
            $date,
            $note,
        ): array {
            $payments = [];
            $freshInvoices = [];

            foreach ($allocations as $index => $allocation)
            {
                /** @var Invoice $invoice */
                $invoice = $allocation['invoice'];
                $sourceReference = $index === 0
                    ? 'statement-line:'.$line->id
                    : 'statement-line:'.$line->id.':'.($index + 1);

                $payment = Payment::withoutGlobalScopes()->create([
                    'team_id' => (int) $account->team_id,
                    'enterprise_id' => $invoice->enterprise_id,
                    'transaction_type' => $isExpense ? TransactionType::EXPENSE : TransactionType::INCOME,
                    'date' => $date,
                    'invoice_id' => (int) $invoice->id,
                    'account_id' => (int) $account->id,
                    'type_id' => $typeId,
                    'amount' => $allocation['amount'],
                    'document_amount' => $allocation['document_amount'],
                    'exchange_rate' => $allocation['exchange_rate'],
                    'remarks' => $note,
                    'status' => 2,
                    'source_provider' => 'manual',
                    'source_reference_id' => $sourceReference,
                ]);

                $newBalance = round(max(0, (float) $invoice->balance - $allocation['applied_to_invoice']), 2);
                $invoice->forceFill(['balance' => $newBalance])->save();

                $payments[] = $payment;
                $freshInvoices[] = $invoice->fresh() ?? $invoice;
            }

            $primaryPayment = $payments[0];

            $line->forceFill([
                'payment_id' => $primaryPayment->id,
                'match_status' => 'matched',
                'reconcile_dismissed_at' => null,
            ])->save();

            $statement = $line->statement;
            if ($statement instanceof BankStatement)
            {
                $validation = $this->statementUploadService->validateAgainstPayments($statement, $account);
                $statement->forceFill([
                    'validation_summary' => array_merge($statement->validation_summary ?? [], $validation),
                ])->save();
            }

            return [
                'payment' => $primaryPayment,
                'payments' => $payments,
                'invoice' => $freshInvoices[0],
                'invoices' => $freshInvoices,
                'line' => $line->fresh(['payment']) ?? $line,
            ];
        });
    }

    /**
     * @param  list<Invoice>  $invoices
     * @return list<array{
     *     invoice: Invoice,
     *     amount: float,
     *     document_amount: float,
     *     exchange_rate: float,
     *     applied_to_invoice: float
     * }>
     */
    private function allocateInvoicePayments(
        array $invoices,
        ?int $accountCurrencyId,
        float $bankAmount,
    ): array {
        foreach ($invoices as $invoice)
        {
            $invoice->loadMissing('currency:id,code');
        }

        if (count($invoices) === 1)
        {
            $invoice = $invoices[0];
            $invoiceCurrencyId = $invoice->currency_id !== null ? (int) $invoice->currency_id : null;
            $sameCurrency = $accountCurrencyId === null
                || $invoiceCurrencyId === null
                || $accountCurrencyId === $invoiceCurrencyId;
            $invoiceBalance = round((float) $invoice->balance, 2);

            // Same currency: bank amount reduces invoice balance 1:1.
            // Cross-currency: settle the remaining document balance and store the implied rate.
            if ($sameCurrency)
            {
                return [[
                    'invoice' => $invoice,
                    'amount' => $bankAmount,
                    'document_amount' => $bankAmount,
                    'exchange_rate' => 1.0,
                    'applied_to_invoice' => $bankAmount,
                ]];
            }

            return [[
                'invoice' => $invoice,
                'amount' => $bankAmount,
                'document_amount' => $invoiceBalance,
                'exchange_rate' => round($bankAmount / $invoiceBalance, 6),
                'applied_to_invoice' => $invoiceBalance,
            ]];
        }

        $allocations = [];
        $remaining = $bankAmount;

        foreach ($invoices as $invoice)
        {
            $invoiceCurrencyId = $invoice->currency_id !== null ? (int) $invoice->currency_id : null;
            $sameCurrency = $accountCurrencyId === null
                || $invoiceCurrencyId === null
                || $accountCurrencyId === $invoiceCurrencyId;

            if (! $sameCurrency)
            {
                throw new RuntimeException(
                    'Para asociar varias facturas todas deben estar en la misma divisa que la cuenta.',
                );
            }

            if ($remaining <= 0.05)
            {
                break;
            }

            $invoiceBalance = round((float) $invoice->balance, 2);
            $allocated = round(min($remaining, $invoiceBalance), 2);

            if ($allocated <= 0)
            {
                continue;
            }

            $allocations[] = [
                'invoice' => $invoice,
                'amount' => $allocated,
                'document_amount' => $allocated,
                'exchange_rate' => 1.0,
                'applied_to_invoice' => $allocated,
            ];
            $remaining = round($remaining - $allocated, 2);
        }

        if ($allocations === [])
        {
            throw new RuntimeException('No se pudo repartir el importe entre las facturas seleccionadas.');
        }

        if ($remaining > 0.05)
        {
            throw new RuntimeException(
                'Las facturas seleccionadas no cubren el importe del movimiento (faltan '
                .number_format($remaining, 2, ',', '.').').',
            );
        }

        return $allocations;
    }

    /**
     * Roll back a reconcile match created from the statement (invoice link or transfer).
     *
     * @return array{line: BankStatementLine, invoice: Invoice|null, deleted_payment_ids: list<int>}
     */
    public function unlinkMatch(BankStatementLine $line, PaymentAccount $account): array
    {
        if (! $line->payment_id)
        {
            throw new RuntimeException('Este movimiento no tiene un pago asociado.');
        }

        $payment = Payment::withoutGlobalScopes()
            ->with('invoice')
            ->find($line->payment_id);

        if (! $payment instanceof Payment)
        {
            throw new RuntimeException('No se encontró el pago asociado al movimiento.');
        }

        $sourceReference = (string) ($payment->source_reference_id ?? '');
        $statementLinePrefix = 'statement-line:'.$line->id;
        $isStatementInvoiceLink = $sourceReference === $statementLinePrefix
            || str_starts_with($sourceReference, $statementLinePrefix.':');
        $isTransferInflow = (string) ($payment->source_provider ?? '') === 'transfer'
            && str_starts_with($sourceReference, 'transfer:')
            && str_ends_with($sourceReference, ':in');

        if (! $isStatementInvoiceLink && ! $isTransferInflow)
        {
            throw new RuntimeException('Solo se pueden desasociar pagos creados desde la conciliación.');
        }

        return DB::transaction(function () use (
            $line,
            $account,
            $payment,
            $isStatementInvoiceLink,
            $sourceReference,
            $statementLinePrefix,
        ): array {
            $deletedPaymentIds = [];
            $invoice = null;

            if ($isStatementInvoiceLink)
            {
                $linkedPayments = Payment::withoutGlobalScopes()
                    ->with('invoice')
                    ->where(function ($query) use ($statementLinePrefix): void
                    {
                        $query->where('source_reference_id', $statementLinePrefix)
                            ->orWhere('source_reference_id', 'like', $statementLinePrefix.':%');
                    })
                    ->orderBy('id')
                    ->get();

                if ($linkedPayments->isEmpty())
                {
                    $linkedPayments = collect([$payment]);
                }

                foreach ($linkedPayments as $linkedPayment)
                {
                    $linkedInvoice = $linkedPayment->invoice;

                    if ($linkedInvoice instanceof Invoice)
                    {
                        if ($invoice === null)
                        {
                            $invoice = $linkedInvoice;
                        }

                        $applied = $linkedPayment->document_amount !== null
                            ? round((float) $linkedPayment->document_amount, 2)
                            : round((float) $linkedPayment->amount, 2);

                        $restoredBalance = round((float) $linkedInvoice->balance + $applied, 2);
                        $invoiceCeiling = $linkedInvoice->total_amount !== null
                            ? round((float) $linkedInvoice->total_amount, 2)
                            : null;

                        if ($invoiceCeiling !== null)
                        {
                            $restoredBalance = min($restoredBalance, $invoiceCeiling);
                        }

                        $linkedInvoice->forceFill(['balance' => $restoredBalance])->save();
                    }

                    $deletedPaymentIds[] = (int) $linkedPayment->id;
                    $linkedPayment->delete();
                }
            } else
            {
                $transferKey = Str::beforeLast(Str::after($sourceReference, 'transfer:'), ':');
                $pairPayments = Payment::withoutGlobalScopes()
                    ->where('source_provider', 'transfer')
                    ->where(function ($query) use ($transferKey): void
                    {
                        $query->where('source_reference_id', 'transfer:'.$transferKey.':in')
                            ->orWhere('source_reference_id', 'transfer:'.$transferKey.':out');
                    })
                    ->get();

                foreach ($pairPayments as $pairPayment)
                {
                    $deletedPaymentIds[] = (int) $pairPayment->id;
                    $pairPayment->delete();
                }
            }

            $line->forceFill([
                'payment_id' => null,
                'match_status' => 'statement_only',
                'reconcile_dismissed_at' => null,
            ])->save();

            $statement = $line->statement;
            if ($statement instanceof BankStatement)
            {
                $validation = $this->statementUploadService->validateAgainstPayments($statement, $account);
                $statement->forceFill([
                    'validation_summary' => array_merge($statement->validation_summary ?? [], $validation),
                ])->save();
            }

            return [
                'line' => $line->fresh() ?? $line,
                'invoice' => $invoice instanceof Invoice ? ($invoice->fresh() ?? $invoice) : null,
                'deleted_payment_ids' => $deletedPaymentIds,
            ];
        });
    }

    public function deleteStatement(BankStatement $statement): void
    {
        DB::transaction(function () use ($statement): void
        {
            $disk = (string) ($statement->disk ?? '');
            $path = (string) ($statement->storage_path ?? '');

            $statement->delete();

            if ($disk !== '' && $path !== '' && Storage::disk($disk)->exists($path))
            {
                Storage::disk($disk)->delete($path);
            }
        });
    }

    private function defaultPaymentTypeId(): ?int
    {
        $query = PaymentType::query()->orderBy('id');

        if (Schema::hasColumn('payment_types', 'is_active'))
        {
            $query->where('is_active', true);
        }

        $preferred = (clone $query)->whereKey(self::BANK_TRANSFER_TYPE_ID)->value('id');

        if ($preferred)
        {
            return (int) $preferred;
        }

        $first = $query->value('id');

        return $first ? (int) $first : null;
    }
}
