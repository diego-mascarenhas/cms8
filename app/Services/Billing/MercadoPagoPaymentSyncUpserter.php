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
        $customerId = null;
        $customerEmail = null;
        if (is_array($payer))
        {
            $payerId = Arr::get($payer, 'id');
            if (filled($payerId))
            {
                $customerId = (string) $payerId;
            }
            $email = strtolower(trim((string) Arr::get($payer, 'email', '')));
            $customerEmail = $email !== '' ? $email : null;
        }

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

        $createdAt = $this->parseDateTime(
            Arr::get($paymentPayload, 'date_approved')
            ?? Arr::get($paymentPayload, 'date_created'),
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
                'invoice_external_id' => null,
                'description' => $description,
                'charge_created_at' => $createdAt,
                'last_synced_at' => now(),
                'raw_payload' => $paymentPayload,
            ],
        );
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
