<?php

namespace App\Services\Billing;

use App\Models\Team;
use Stripe\StripeClient;

class TeamUsageInvoiceStripeGateway
{
    public function createDraftInvoice(Team $team, string $currency, array $metadata): object
    {
        return $this->client()->invoices->create([
            'customer' => $team->stripe_id,
            'auto_advance' => false,
            'pending_invoice_items_behavior' => 'exclude',
            'collection_method' => 'charge_automatically',
            'currency' => strtolower($currency),
            'metadata' => $metadata,
        ], [
            'idempotency_key' => $this->idempotencyKey($team, $metadata),
        ]);
    }

    public function addInvoiceItem(
        string $customerId,
        string $invoiceId,
        string $description,
        int $amountCents,
        string $currency,
    ): object {
        return $this->client()->invoiceItems->create([
            'customer' => $customerId,
            'invoice' => $invoiceId,
            'currency' => strtolower($currency),
            'description' => $description,
            'amount' => $amountCents,
        ]);
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('cashier.secret'));
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function idempotencyKey(Team $team, array $metadata): string
    {
        return implode('-', [
            'usage',
            (string) $team->id,
            $metadata['humano_period_from'] ?? '',
            $metadata['humano_period_to'] ?? '',
            $metadata['humano_kind'] ?? 'cycle',
        ]);
    }
}
