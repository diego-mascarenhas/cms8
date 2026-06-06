<?php

namespace App\Services\Billing;

use App\Models\InvoiceSync;
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
