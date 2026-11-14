<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Illuminate\Support\Facades\DB;
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
}
