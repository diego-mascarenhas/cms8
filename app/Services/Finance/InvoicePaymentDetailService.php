<?php

namespace App\Services\Finance;

use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Services\Billing\MercadoPagoPaymentImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoicePaymentDetailService
{
    public function __construct(
        private readonly MercadoPagoPaymentImportService $mercadoPagoPaymentImportService,
    ) {}

    /**
     * @return Collection<int, array{
     *     id: int|null,
     *     status: int|null,
     *     date: \Carbon\CarbonInterface,
     *     amount: float,
     *     currency_code: string,
     *     method: string|null,
     *     account: string|null,
     *     status_html: string|null,
     *     remarks: string|null,
     *     is_income: bool,
     *     mercadopago: array{payer_name?: string, payment_channel?: string, operation_number?: string, identification_code?: string, cbu?: string}|null,
     * }>
     */
    public function forInvoice(Invoice $invoice): Collection
    {
        $this->mercadoPagoPaymentImportService->importOutOfBandLinkForStripeInvoice($invoice);

        $payments = $this->resolvePayments($invoice);

        if ($payments->isNotEmpty())
        {
            return $payments->map(function (Payment $payment) use ($invoice): array
            {
                $currencyCode = strtoupper(
                    (string) ($payment->account?->currency?->code ?? $invoice->currency_code),
                );

                return [
                    'id' => $payment->id,
                    'status' => (int) $payment->status,
                    'date' => $payment->date,
                    'amount' => (float) $payment->amount,
                    'currency_code' => $currencyCode,
                    'method' => $payment->type?->name,
                    'account' => $payment->account?->name,
                    'status_html' => $payment->status_label,
                    'remarks' => filled($payment->remarks) ? (string) $payment->remarks : null,
                    'is_income' => $payment->transaction_type === TransactionType::INCOME,
                    'mercadopago' => $this->mercadoPagoDetails($payment),
                ];
            });
        }

        return $this->detailsFromStripePaymentSyncs($invoice);
    }

    /**
     * @return array{payer_name?: string, payment_channel?: string, operation_number?: string, identification_code?: string, cbu?: string}|null
     */
    private function mercadoPagoDetails(Payment $payment): ?array
    {
        if (strtolower((string) $payment->source_provider) !== 'mercadopago')
        {
            return null;
        }

        $externalId = strtok((string) $payment->source_reference_id, ':');
        if (! is_string($externalId) || $externalId === '')
        {
            return null;
        }

        $sync = PaymentSync::query()
            ->where('team_id', $payment->team_id)
            ->where('provider', 'mercadopago')
            ->where('external_id', $externalId)
            ->first();

        if (! $sync instanceof PaymentSync)
        {
            return null;
        }

        $details = array_filter([
            'payer_name' => $sync->displayPayerName(),
            'payment_channel' => $sync->displayPaymentChannel(),
            'operation_number' => $externalId,
            'identification_code' => $sync->identificationCode(),
            'cbu' => $sync->displayPayerCbu(),
        ], fn (?string $value): bool => filled($value));

        return $details !== [] ? $details : null;
    }

    /**
     * @return Collection<int, Payment>
     */
    private function resolvePayments(Invoice $invoice): Collection
    {
        return Payment::query()
            ->where('status', '!=', 0)
            ->where(function (Builder $query) use ($invoice): void
            {
                $query->where('invoice_id', $invoice->id);

                if ($invoice->source_provider === 'stripe' && filled($invoice->source_reference_id))
                {
                    $query->orWhereIn('source_reference_id', PaymentSync::query()
                        ->where('team_id', $invoice->team_id)
                        ->where('invoice_external_id', $invoice->source_reference_id)
                        ->select('external_id'));
                }
            })
            ->with([
                'type',
                'account' => fn ($query) => $query->withoutGlobalScope('activeStatus')->with('currency'),
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     id: int|null,
     *     status: int|null,
     *     date: \Carbon\CarbonInterface,
     *     amount: float,
     *     currency_code: string,
     *     method: string|null,
     *     account: string|null,
     *     status_html: string|null,
     *     remarks: string|null,
     *     is_income: bool,
     * }>
     */
    private function detailsFromStripePaymentSyncs(Invoice $invoice): Collection
    {
        if ($invoice->source_provider !== 'stripe' || ! filled($invoice->source_reference_id))
        {
            return collect();
        }

        return PaymentSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('invoice_external_id', $invoice->source_reference_id)
            ->where('status', 'succeeded')
            ->orderByDesc('charge_created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (PaymentSync $sync): array
            {
                $currency = strtoupper((string) $sync->currency);
                $amount = $this->majorAmountFromCents((int) $sync->amount_net_cents, $currency);

                return [
                    'id' => null,
                    'status' => null,
                    'date' => $sync->charge_created_at ?? now(),
                    'amount' => $amount,
                    'currency_code' => $currency,
                    'method' => 'Stripe',
                    'account' => 'Stripe',
                    'status_html' => '<span class="badge rounded-pill bg-label-success">'.e(__('Approved')).'</span>',
                    'remarks' => null,
                    'is_income' => true,
                    'mercadopago' => null,
                ];
            });
    }

    private function majorAmountFromCents(int $cents, string $currency): float
    {
        $zeroDecimal = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];
        $divisor = in_array($currency, $zeroDecimal, true) ? 1 : 100;
        $value = $divisor === 1 ? (float) $cents : round($cents / 100, 2);

        return max(0.0, $value);
    }
}
