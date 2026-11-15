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
                'remarks' => $note,
                'status' => 2,
                'source_provider' => 'manual',
                'source_reference_id' => 'statement-line:'.$line->id,
            ]);

            $newBalance = round(max(0, (float) $invoice->balance - $amount), 2);
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
