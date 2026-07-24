<?php

namespace App\Services\Billing;

use App\Models\InvoiceSync;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeInvoiceSyncRefresher
{
    public function __construct(
        private readonly StripeInvoiceSyncUpserter $upserter,
    ) {}

    public function refreshFromStripe(StripeClient $client, int $teamId, string $externalId): ?InvoiceSync
    {
        $externalId = trim($externalId);
        if ($externalId === '')
        {
            return null;
        }

        $payload = $client->invoices->retrieve($externalId, [
            'expand' => ['customer', 'subscription'],
        ])->toArray();

        return $this->upserter->upsertFromPayload($teamId, $payload);
    }

    /**
     * Pull recent paid Stripe invoices for a customer into invoice_syncs.
     * Used when assigning Mercado Pago transfers so paid-unlinked invoices appear.
     */
    public function syncPaidInvoicesForCustomer(int $teamId, string $customerId, int $limit = 40): int
    {
        $customerId = trim($customerId);
        if ($customerId === '' || ! str_starts_with($customerId, 'cus_'))
        {
            return 0;
        }

        $team = Team::query()->find($teamId);
        if (! $team instanceof Team)
        {
            return 0;
        }

        $secret = trim((string) $team->getSetting('stripe_secret'));
        if ($secret === '')
        {
            return 0;
        }

        $client = new StripeClient($secret);
        $limit = max(1, min(100, $limit));
        $synced = 0;

        try
        {
            $page = $client->invoices->all([
                'customer' => $customerId,
                'status' => 'paid',
                'limit' => $limit,
            ]);

            foreach ($page->data as $invoice)
            {
                $payload = is_object($invoice) && method_exists($invoice, 'toArray')
                    ? $invoice->toArray()
                    : (array) $invoice;

                if ($this->upserter->upsertFromPayload($teamId, $payload) !== null)
                {
                    $synced++;
                }
            }
        } catch (\Throwable $exception)
        {
            Log::warning('Stripe paid invoices sync for customer failed', [
                'team_id' => $teamId,
                'customer_id' => $customerId,
                'message' => $exception->getMessage(),
            ]);
        }

        return $synced;
    }

    public function reconcileStaleOpenInvoices(
        StripeClient $client,
        int $teamId,
        int $limit = 40,
    ): int {
        $limit = max(1, $limit);

        $rows = InvoiceSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'stripe')
            ->where('status', 'open')
            ->orderBy('last_synced_at')
            ->limit($limit)
            ->get();

        $refreshed = 0;

        foreach ($rows as $row)
        {
            if (! $row instanceof InvoiceSync)
            {
                continue;
            }

            try
            {
                $sync = $this->refreshFromStripe($client, $teamId, (string) $row->external_id);
                if ($sync !== null)
                {
                    $refreshed++;
                }
            } catch (\Throwable $exception)
            {
                Log::warning('Stripe invoice stale reconcile failed', [
                    'team_id' => $teamId,
                    'external_id' => $row->external_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $refreshed;
    }
}
