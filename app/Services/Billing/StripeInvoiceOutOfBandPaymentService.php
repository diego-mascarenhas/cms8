<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeInvoiceOutOfBandPaymentService
{
    public function __construct(
        private readonly StripeInvoiceSyncRefresher $syncRefresher,
        private readonly StripeInvoiceCoreImportService $coreImportService,
    ) {}

    /**
     * Mark a Stripe-sourced invoice as paid out of band, storing payment type + reference
     * on the Stripe invoice metadata (Dashboard "Record payment" equivalent fields).
     */
    public function markPaidFromPayment(Payment $payment): bool
    {
        if (! $payment->invoice_id)
        {
            return false;
        }

        $invoice = Invoice::withoutGlobalScopes()
            ->whereKey($payment->invoice_id)
            ->first();

        if (! $invoice instanceof Invoice)
        {
            return false;
        }

        if (! $this->isStripeOpenInvoice($invoice))
        {
            return false;
        }

        $amount = round((float) $payment->amount, 2);
        $remaining = round((float) $invoice->balance, 2);
        if ($amount <= 0 || $remaining <= 0)
        {
            return false;
        }

        // Classic paid_out_of_band pays the full remaining balance.
        if (abs($amount - $remaining) > 0.05)
        {
            Log::info('Stripe OOB payment skipped: amount does not cover remaining balance', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'remaining' => $remaining,
            ]);

            return false;
        }

        $team = Team::query()->find($invoice->team_id);
        if (! $team instanceof Team)
        {
            return false;
        }

        $secret = trim((string) $team->getSetting('stripe_secret'));
        if ($secret === '')
        {
            Log::warning('Stripe OOB payment skipped: missing stripe_secret', [
                'team_id' => $invoice->team_id,
                'invoice_id' => $invoice->id,
            ]);

            return false;
        }

        $externalId = (string) $invoice->source_reference_id;
        $client = $this->makeClient($secret);
        $paymentType = $this->resolvePaymentTypeLabel($payment);
        $reference = $this->resolvePaymentReference($payment);
        $mercadoPagoId = $this->resolveMercadoPagoId($payment);

        try
        {
            $client->invoices->update($externalId, [
                'metadata' => array_filter([
                    'humano_payment_id' => (string) $payment->id,
                    'payment_method' => $paymentType,
                    'payment_reference' => $reference,
                    'mercadopago_id' => $mercadoPagoId,
                    'payment_notes' => filled($payment->remarks) ? (string) $payment->remarks : null,
                    'source_provider' => (string) $payment->source_provider,
                ], fn ($value) => $value !== null && $value !== ''),
            ]);

            $client->invoices->pay($externalId, [
                'paid_out_of_band' => true,
            ]);
        } catch (ApiErrorException $exception)
        {
            Log::warning('Stripe OOB payment failed', [
                'invoice_id' => $invoice->id,
                'external_id' => $externalId,
                'payment_id' => $payment->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        $sync = $this->syncRefresher->refreshFromStripe($client, (int) $invoice->team_id, $externalId);
        if ($sync !== null)
        {
            $this->coreImportService->importFromSyncRow($sync, false, false, false);
        }

        return true;
    }

    protected function makeClient(string $secret): StripeClient
    {
        return new StripeClient($secret);
    }

    private function isStripeOpenInvoice(Invoice $invoice): bool
    {
        if (strtolower((string) $invoice->source_provider) !== 'stripe')
        {
            return false;
        }

        $externalId = trim((string) $invoice->source_reference_id);

        return $externalId !== '' && str_starts_with($externalId, 'in_');
    }

    private function resolvePaymentTypeLabel(Payment $payment): string
    {
        if ($payment->relationLoaded('type') && $payment->type instanceof PaymentType)
        {
            return $payment->type->display_name;
        }

        if ($payment->type_id)
        {
            $type = PaymentType::query()->find($payment->type_id);
            if ($type instanceof PaymentType)
            {
                return $type->display_name;
            }
        }

        return strtolower((string) $payment->source_provider) === 'mercadopago'
            ? 'MercadoPago'
            : 'Manual';
    }

    private function resolvePaymentReference(Payment $payment): ?string
    {
        if (preg_match('/Ref:\s*([^\s·]+)/u', (string) $payment->remarks, $matches) === 1)
        {
            return $matches[1];
        }

        $sourceReference = trim((string) $payment->source_reference_id);
        if ($sourceReference !== '')
        {
            return explode(':', $sourceReference, 2)[0];
        }

        return null;
    }

    private function resolveMercadoPagoId(Payment $payment): ?string
    {
        if (strtolower((string) $payment->source_provider) !== 'mercadopago')
        {
            return null;
        }

        $sourceReference = trim((string) $payment->source_reference_id);
        if ($sourceReference === '')
        {
            return null;
        }

        return explode(':', $sourceReference, 2)[0];
    }
}
