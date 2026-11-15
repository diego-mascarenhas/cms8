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
     * Create a payment on an existing invoice and match the bank statement line to it.
     *
     * @return array{payment: Payment, invoice: Invoice, line: BankStatementLine}
     */
    public function linkToInvoice(
        BankStatementLine $line,
        Invoice $invoice,
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

        if ((int) $invoice->team_id !== (int) $account->team_id)
        {
            throw new RuntimeException('La factura no pertenece al mismo equipo.');
        }

        $amount = abs((float) $line->amount);

        if ($amount <= 0)
        {
            throw new RuntimeException('El movimiento no tiene importe para asociar.');
        }

        $isExpense = (float) $line->amount < 0;
        $expectedOperation = $isExpense ? 'buy' : 'sell';

        if ((string) $invoice->operation !== $expectedOperation)
        {
            throw new RuntimeException($isExpense
                ? 'Para un egreso del extracto elegí un gasto (factura de compra).'
                : 'Para un ingreso del extracto elegí una factura de venta.');
        }

        $typeId = $paymentTypeId ?: $this->defaultPaymentTypeId();

        if (! $typeId)
        {
            throw new RuntimeException('No hay formas de pago disponibles.');
        }

        $account->loadMissing('currency:id,code');
        $invoice->loadMissing('currency:id,code');

        $accountCurrencyId = $account->currency_id !== null ? (int) $account->currency_id : null;
        $invoiceCurrencyId = $invoice->currency_id !== null ? (int) $invoice->currency_id : null;
        $sameCurrency = $accountCurrencyId === null
            || $invoiceCurrencyId === null
            || $accountCurrencyId === $invoiceCurrencyId;

        $invoiceBalance = round((float) $invoice->balance, 2);

        if ($invoiceBalance <= 0)
        {
            throw new RuntimeException('La factura no tiene saldo pendiente.');
        }

        // Same currency: bank amount reduces invoice balance 1:1.
        // Cross-currency: bank amount is in account currency; settle the invoice
        // remaining balance in document currency and store the implied rate
        // (account units per 1 document unit), matching expense payment UX.
        if ($sameCurrency)
        {
            $documentAmount = $amount;
            $exchangeRate = 1.0;
            $appliedToInvoice = $amount;
        } else
        {
            $documentAmount = $invoiceBalance;
            $exchangeRate = round($amount / $documentAmount, 6);
            $appliedToInvoice = $documentAmount;
        }

        $date = $line->occurred_at?->toDateString() ?? now()->toDateString();
        $concept = trim((string) ($line->reference ?: $line->description ?: ''));
        $note = $remarks !== null && trim($remarks) !== ''
            ? trim($remarks)
            : ($concept !== '' ? $concept : __('Pago desde extracto bancario'));

        return DB::transaction(function () use (
            $line,
            $invoice,
            $account,
            $amount,
            $documentAmount,
            $exchangeRate,
            $appliedToInvoice,
            $isExpense,
            $typeId,
            $date,
            $note,
        ): array {
            $payment = Payment::withoutGlobalScopes()->create([
                'team_id' => (int) $account->team_id,
                'enterprise_id' => $invoice->enterprise_id,
                'transaction_type' => $isExpense ? TransactionType::EXPENSE : TransactionType::INCOME,
                'date' => $date,
                'invoice_id' => (int) $invoice->id,
                'account_id' => (int) $account->id,
                'type_id' => $typeId,
                'amount' => $amount,
                'document_amount' => $documentAmount,
                'exchange_rate' => $exchangeRate,
                'remarks' => $note,
                'status' => 2,
                'source_provider' => 'manual',
                'source_reference_id' => 'statement-line:'.$line->id,
            ]);

            $newBalance = round(max(0, (float) $invoice->balance - $appliedToInvoice), 2);
            $invoice->forceFill(['balance' => $newBalance])->save();

            $line->forceFill([
                'payment_id' => $payment->id,
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
                'payment' => $payment,
                'invoice' => $invoice->fresh() ?? $invoice,
                'line' => $line->fresh(['payment']) ?? $line,
            ];
        });
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
        $isStatementInvoiceLink = $sourceReference === 'statement-line:'.$line->id;
        $isTransferInflow = (string) ($payment->source_provider ?? '') === 'transfer'
            && str_starts_with($sourceReference, 'transfer:')
            && str_ends_with($sourceReference, ':in');

        if (! $isStatementInvoiceLink && ! $isTransferInflow)
        {
            throw new RuntimeException('Solo se pueden desasociar pagos creados desde la conciliación.');
        }

        return DB::transaction(function () use ($line, $account, $payment, $isStatementInvoiceLink, $sourceReference): array
        {
            $deletedPaymentIds = [];
            $invoice = null;

            if ($isStatementInvoiceLink)
            {
                $invoice = $payment->invoice;

                if ($invoice instanceof Invoice)
                {
                    $applied = $payment->document_amount !== null
                        ? round((float) $payment->document_amount, 2)
                        : round((float) $payment->amount, 2);

                    $restoredBalance = round((float) $invoice->balance + $applied, 2);
                    $invoiceCeiling = $invoice->total_amount !== null
                        ? round((float) $invoice->total_amount, 2)
                        : null;

                    if ($invoiceCeiling !== null)
                    {
                        $restoredBalance = min($restoredBalance, $invoiceCeiling);
                    }

                    $invoice->forceFill(['balance' => $restoredBalance])->save();
                }

                $deletedPaymentIds[] = (int) $payment->id;
                $payment->delete();
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
