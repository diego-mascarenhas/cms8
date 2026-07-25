<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class MercadoPagoPaymentMatchUndoService
{
    /**
     * Undo a Mercado Pago → Humano payment match: delete local payments and clear MP metadata.
     * Does not reverse Stripe paid_out_of_band.
     *
     * @return array{deleted_payments: int, cleared_invoice_syncs: int, stripe_updates: int}
     */
    public function undo(PaymentSync $sync): array
    {
        $externalId = trim((string) $sync->external_id);
        if ($externalId === '' || strtolower((string) $sync->provider) !== 'mercadopago')
        {
            return [
                'deleted_payments' => 0,
                'cleared_invoice_syncs' => 0,
                'stripe_updates' => 0,
            ];
        }

        return DB::transaction(function () use ($sync, $externalId): array
        {
            $payments = Payment::withoutGlobalScopes()
                ->where('team_id', $sync->team_id)
                ->where('source_provider', 'mercadopago')
                ->where(function ($query) use ($externalId): void
                {
                    $query->where('source_reference_id', $externalId)
                        ->orWhere('source_reference_id', 'like', $externalId.':%');
                })
                ->get();

            $invoiceIds = $payments
                ->pluck('invoice_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $deleted = $payments->count();
            foreach ($payments as $payment)
            {
                $payment->delete();
            }

            $cleared = 0;
            $stripeUpdates = 0;

            if ($invoiceIds === [])
            {
                return [
                    'deleted_payments' => $deleted,
                    'cleared_invoice_syncs' => 0,
                    'stripe_updates' => 0,
                ];
            }

            $invoices = Invoice::withoutGlobalScopes()
                ->where('team_id', $sync->team_id)
                ->whereIn('id', $invoiceIds)
                ->get();

            foreach ($invoices as $invoice)
            {
                if ($this->clearLocalInvoiceSyncMetadata($invoice, $externalId))
                {
                    $cleared++;
                }

                if ($this->clearStripeInvoiceMetadata($invoice, $externalId))
                {
                    $stripeUpdates++;
                }
            }

            return [
                'deleted_payments' => $deleted,
                'cleared_invoice_syncs' => $cleared,
                'stripe_updates' => $stripeUpdates,
            ];
        });
    }

    private function clearLocalInvoiceSyncMetadata(Invoice $invoice, string $externalId): bool
    {
        $externalStripeId = trim((string) $invoice->source_reference_id);
        if ($externalStripeId === '' || strtolower((string) $invoice->source_provider) !== 'stripe')
        {
            return false;
        }

        $sync = InvoiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $externalStripeId)
            ->orderByDesc('id')
            ->first();

        if (! $sync instanceof InvoiceSync)
        {
            return false;
        }

        $payload = is_array($sync->raw_payload) ? $sync->raw_payload : [];
        $metadata = data_get($payload, 'metadata', []);
        if (! is_array($metadata))
        {
            $metadata = [];
        }

        $changed = false;
        foreach (['mercadopago_id', 'mercadopago_payment_id', 'payment_reference'] as $key)
        {
            $value = trim((string) ($metadata[$key] ?? ''));
            if ($value === '')
            {
                continue;
            }

            if ($value === $externalId || str_starts_with($value, $externalId.':'))
            {
                unset($metadata[$key]);
                $changed = true;
            }
        }

        if (! $changed)
        {
            return false;
        }

        $payload['metadata'] = $metadata;
        $sync->forceFill(['raw_payload' => $payload])->save();

        return true;
    }

    private function clearStripeInvoiceMetadata(Invoice $invoice, string $externalId): bool
    {
        if (strtolower((string) $invoice->source_provider) !== 'stripe')
        {
            return false;
        }

        $stripeInvoiceId = trim((string) $invoice->source_reference_id);
        if ($stripeInvoiceId === '' || ! str_starts_with($stripeInvoiceId, 'in_'))
        {
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
            return false;
        }

        try
        {
            $client = $this->makeClient($secret);
            $client->invoices->update($stripeInvoiceId, [
                'metadata' => [
                    'mercadopago_id' => '',
                    'mercadopago_payment_id' => '',
                    'payment_reference' => '',
                    'humano_payment_id' => '',
                    'source_provider' => '',
                ],
            ]);
        } catch (ApiErrorException $exception)
        {
            Log::warning('MercadoPago match undo: Stripe metadata clear failed', [
                'invoice_id' => $invoice->id,
                'external_id' => $stripeInvoiceId,
                'mp_external_id' => $externalId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    protected function makeClient(string $secret): StripeClient
    {
        return new StripeClient($secret);
    }
}
