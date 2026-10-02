<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Team;
use InvalidArgumentException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class SellInvoiceCardCharge
{
    /**
     * Create the Stripe invoice and charge the customer's default card.
     */
    public function charge(Invoice $invoice): string
    {
        $invoice->loadMissing(['enterprise', 'currency', 'team', 'items']);

        $customerId = trim((string) ($invoice->enterprise?->code ?? ''));
        if (! str_starts_with($customerId, 'cus_'))
        {
            throw new InvalidArgumentException('Este cliente no tiene un cliente de Stripe para cobrar la tarjeta.');
        }

        $team = $invoice->team;
        if (! $team instanceof Team)
        {
            throw new InvalidArgumentException('No se encontró el equipo de la factura.');
        }

        $secret = trim((string) $team->getSetting('stripe_secret'));
        if ($secret === '')
        {
            throw new InvalidArgumentException('Stripe no está configurado para este equipo.');
        }

        $currency = strtolower(trim((string) ($invoice->currency?->code ?? 'eur')));
        $amountCents = (int) round(((float) $invoice->total_amount) * 100);
        if ($amountCents < 1)
        {
            throw new InvalidArgumentException('El importe de la factura no se puede cobrar.');
        }

        $description = trim((string) ($invoice->items->first()?->description ?? ''));
        if ($description === '')
        {
            $description = 'Factura '.$invoice->number;
        }

        $stripe = new StripeClient($secret);

        try
        {
            $stripeInvoice = $stripe->invoices->create([
                'customer' => $customerId,
                'auto_advance' => false,
                'pending_invoice_items_behavior' => 'exclude',
                'collection_method' => 'charge_automatically',
                'currency' => $currency,
                'metadata' => [
                    'humano_invoice_id' => (string) $invoice->id,
                ],
            ]);

            $stripe->invoiceItems->create([
                'customer' => $customerId,
                'invoice' => $stripeInvoice->id,
                'currency' => $currency,
                'description' => $description,
                'amount' => $amountCents,
            ]);

            $stripeInvoice = $stripe->invoices->finalizeInvoice($stripeInvoice->id);
            if (($stripeInvoice->status ?? null) !== 'paid')
            {
                $stripeInvoice = $stripe->invoices->pay($stripeInvoice->id);
            }
        } catch (ApiErrorException $exception)
        {
            throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
        }

        $invoice->forceFill([
            'source_provider' => 'stripe',
            'source_reference_id' => (string) $stripeInvoice->id,
            'source_synced_at' => now(),
        ])->save();

        return (string) ($stripeInvoice->status ?? '');
    }
}
