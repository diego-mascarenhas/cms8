<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class PaymentInvoiceLinkService
{
    /**
     * @return Builder<Invoice>
     */
    public function invoicesQueryForPayment(Payment $payment): Builder
    {
        $user = auth()->user();
        $operation = $payment->transaction_type === TransactionType::EXPENSE ? 'buy' : 'sell';

        return Invoice::query()
            ->with(['enterprise', 'currency'])
            ->where('balance', '>', 0)
            ->where('operation', $operation)
            ->when(
                $payment->enterprise_id,
                fn (Builder $query) => $query->where('enterprise_id', $payment->enterprise_id),
            )
            ->when(
                $user && $user->hasRole('collaborator') && ! $user->hasRole('admin'),
                fn (Builder $query) => $query->whereHas(
                    'enterprise',
                    fn (Builder $enterpriseQuery) => $enterpriseQuery->where('responsible_id', $user->id),
                ),
            )
            ->orderByDesc('date')
            ->orderByDesc('id');
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function invoicesForPayment(Payment $payment, int $limit = 1000): Collection
    {
        return $this->invoicesQueryForPayment($payment)
            ->limit($limit)
            ->get();
    }

    public function linkPaymentToInvoice(Payment $payment, Invoice $invoice): void
    {
        if ($payment->invoice_id)
        {
            throw ValidationException::withMessages([
                'invoice_id' => __('payment_invoice.link.errors.already_linked'),
            ]);
        }

        if ((float) $invoice->balance <= 0)
        {
            throw ValidationException::withMessages([
                'invoice_id' => __('payment_invoice.link.errors.no_balance'),
            ]);
        }

        $expectedOperation = $payment->transaction_type === TransactionType::EXPENSE ? 'buy' : 'sell';
        if ($invoice->operation !== $expectedOperation)
        {
            throw ValidationException::withMessages([
                'invoice_id' => __('payment_invoice.link.errors.operation_mismatch'),
            ]);
        }

        if ($payment->enterprise_id !== null && (int) $invoice->enterprise_id !== (int) $payment->enterprise_id)
        {
            throw ValidationException::withMessages([
                'invoice_id' => __('payment_invoice.link.errors.enterprise_mismatch'),
            ]);
        }

        $payment->invoice_id = $invoice->id;

        if ($payment->enterprise_id === null && $invoice->enterprise_id !== null)
        {
            $payment->enterprise_id = $invoice->enterprise_id;
        }

        $payment->save();
    }
}
