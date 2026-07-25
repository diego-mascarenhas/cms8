<?php

namespace App\Services\Billing;

use App\Models\PaymentSync;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class MercadoPagoPaymentSyncUpserter
{
    /**
     * @param  array<string, mixed>  $paymentPayload
     */
    public function upsertFromPayload(int $teamId, array $paymentPayload): ?PaymentSync
    {
        $externalId = trim((string) Arr::get($paymentPayload, 'id'));
        if ($externalId === '')
        {
            return null;
        }

        $status = strtolower(trim((string) Arr::get($paymentPayload, 'status', '')));
        $currency = strtoupper(trim((string) Arr::get($paymentPayload, 'currency_id', 'ARS')));

        $amountMajor = (float) Arr::get($paymentPayload, 'transaction_amount', 0);
        $refundedMajor = (float) Arr::get($paymentPayload, 'transaction_amount_refunded', 0);
        $amountCents = $this->majorToCents($amountMajor, $currency);
        $refundedCents = $this->majorToCents($refundedMajor, $currency);
        $netCents = max(0, $amountCents - $refundedCents);

        $payer = Arr::get($paymentPayload, 'payer', []);
        [$customerId, $customerEmail] = $this->resolveCustomerIdentity($paymentPayload, is_array($payer) ? $payer : []);

        $description = Arr::get($paymentPayload, 'description');
        if (! is_string($description) || trim($description) === '')
        {
            $description = Arr::get($paymentPayload, 'external_reference');
        }
        if (is_string($description))
        {
            $description = trim($description) !== '' ? trim($description) : null;
        } else
        {
            $description = null;
        }

        $invoiceExternalId = $this->resolveInvoiceExternalId($paymentPayload);

        $createdAt = $this->parseDateTime(
            Arr::get($paymentPayload, 'date_approved')
            ?? Arr::get($paymentPayload, 'date_created'),
        );

        $existing = PaymentSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'mercadopago')
            ->where('external_id', $externalId)
            ->first();

        $rawPayload = $this->mergePreservedSettlementPayer(
            $paymentPayload,
            is_array($existing?->raw_payload) ? $existing->raw_payload : [],
        );

        return PaymentSync::query()->updateOrCreate(
            [
                'team_id' => $teamId,
                'provider' => 'mercadopago',
                'external_id' => $externalId,
            ],
            [
                'customer_id' => $customerId,
                'customer_email' => $customerEmail,
                'status' => $status !== '' ? $status : null,
                'currency' => $currency !== '' ? $currency : 'ARS',
                'amount_cents' => $amountCents,
                'amount_refunded_cents' => $refundedCents,
                'amount_net_cents' => $netCents,
                'invoice_external_id' => $invoiceExternalId,
                'description' => $description,
                'charge_created_at' => $createdAt,
                'last_synced_at' => now(),
                'raw_payload' => $rawPayload,
            ],
        );
    }

    /**
     * Keep settlement_payer enrichment when Payments API overwrites raw_payload.
     *
     * @param  array<string, mixed>  $paymentPayload
     * @param  array<string, mixed>  $existingPayload
     * @return array<string, mixed>
     */
    private function mergePreservedSettlementPayer(array $paymentPayload, array $existingPayload): array
    {
        $merged = $paymentPayload;
        $existing = Arr::get($existingPayload, PaymentSync::RAW_SETTLEMENT_PAYER_KEY, []);
        if (! is_array($existing) || $existing === [])
        {
            return $merged;
        }

        $incoming = Arr::get($merged, PaymentSync::RAW_SETTLEMENT_PAYER_KEY, []);
        $incoming = is_array($incoming) ? $incoming : [];

        // Existing enrichment wins over any accidental key from the API payload.
        $merged[PaymentSync::RAW_SETTLEMENT_PAYER_KEY] = array_replace_recursive($incoming, $existing);

        return $merged;
    }

    /**
     * Bank transfers into the MP account (account_fund) often list the collector as "payer".
     * That is not the end client — leave identity empty so UI/import do not treat it as a customer.
     *
     * @param  array<string, mixed>  $paymentPayload
     * @param  array<string, mixed>  $payer
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveCustomerIdentity(array $paymentPayload, array $payer): array
    {
        if ($this->isCollectorSelfTransfer($paymentPayload, $payer))
        {
            return [null, null];
        }

        $customerId = null;
        $customerEmail = null;

        $payerId = Arr::get($payer, 'id');
        if (filled($payerId))
        {
            $customerId = (string) $payerId;
        }

        $email = strtolower(trim((string) Arr::get($payer, 'email', '')));
        $customerEmail = $email !== '' ? $email : null;

        return [$customerId, $customerEmail];
    }

    /**
     * @param  array<string, mixed>  $paymentPayload
     * @param  array<string, mixed>  $payer
     */
    private function isCollectorSelfTransfer(array $paymentPayload, array $payer): bool
    {
        $operationType = strtolower(trim((string) Arr::get($paymentPayload, 'operation_type', '')));
        if ($operationType === 'account_fund')
        {
            return true;
        }

        $payerId = trim((string) Arr::get($payer, 'id', ''));
        $collectorId = trim((string) Arr::get($paymentPayload, 'collector_id', ''));

        return $payerId !== '' && $collectorId !== '' && $payerId === $collectorId;
    }

    /**
     * Prefer explicit invoice pointers from Mercado Pago (not generic bank descriptions).
     *
     * @param  array<string, mixed>  $paymentPayload
     */
    private function resolveInvoiceExternalId(array $paymentPayload): ?string
    {
        $candidates = [
            Arr::get($paymentPayload, 'external_reference'),
            Arr::get($paymentPayload, 'metadata.invoice_id'),
            Arr::get($paymentPayload, 'metadata.humano_invoice_id'),
            Arr::get($paymentPayload, 'metadata.invoice_number'),
        ];

        foreach ($candidates as $candidate)
        {
            if (! is_string($candidate) && ! is_numeric($candidate))
            {
                continue;
            }

            $value = trim((string) $candidate);
            if ($value === '' || $this->isGenericPaymentLabel($value))
            {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function isGenericPaymentLabel(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));

        return in_array($normalized, [
            'bank transfer',
            'varios',
            'payment',
            'pago',
            'transferencia',
        ], true);
    }

    private function majorToCents(float $amount, string $currency): int
    {
        $zeroDecimal = ['CLP', 'UYU', 'PYG'];
        if (in_array(strtoupper($currency), $zeroDecimal, true))
        {
            return (int) round(abs($amount));
        }

        return (int) round(abs($amount) * 100);
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '')
        {
            return null;
        }

        try
        {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable)
        {
            return null;
        }
    }
}
