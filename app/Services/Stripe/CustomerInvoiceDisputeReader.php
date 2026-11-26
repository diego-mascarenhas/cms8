<?php

namespace App\Services\Stripe;

use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use Stripe\StripeClient;
use Throwable;

class CustomerInvoiceDisputeReader
{
    /**
     * Stripe invoice ids with a dispute that did not end in the merchant's favour.
     *
     * @return array<string, string>
     */
    public function forCustomer(?Team $team, string $customerId): array
    {
        $customerId = trim($customerId);
        if (! $team || ! str_starts_with($customerId, 'cus_'))
        {
            return [];
        }

        $secret = trim((string) $team->getSetting('stripe_secret'));
        if (! str_starts_with($secret, 'sk_') && ! str_starts_with($secret, 'rk_'))
        {
            return [];
        }

        $cacheKey = 'client-invoice-disputes:v2:'.$team->id.':'.$customerId;
        $cached = Cache::get($cacheKey);
        if (is_array($cached))
        {
            return $this->cachedStatuses($cached);
        }

        try
        {
            $statuses = $this->fetch($secret, $customerId);
        } catch (Throwable $exception)
        {
            report($exception);

            return [];
        }

        Cache::put($cacheKey, ['statuses' => $statuses], now()->addMinutes(10));

        return $statuses;
    }

    /**
     * @param  iterable<int, object>  $invoices
     * @return array<string, string>
     */
    public function statusesFromInvoices(iterable $invoices): array
    {
        $statuses = [];
        foreach ($invoices as $invoice)
        {
            $id = trim((string) ($invoice->id ?? ''));
            $charge = $invoice->charge ?? null;
            if ($id === '' || ! is_object($charge))
            {
                continue;
            }

            $status = $this->disputeStatus($charge);
            if ($status !== null && $this->blocksCollection($status))
            {
                $statuses[$id] = $status;
            }
        }

        return $statuses;
    }

    public function blocksCollection(string $status): bool
    {
        return ! in_array(strtolower(trim($status)), ['', 'won', 'warning_closed'], true);
    }

    /**
     * @return array<string, string>
     */
    private function fetch(string $secret, string $customerId): array
    {
        $client = new StripeClient($secret);
        $page = $client->invoices->all([
            'customer' => $customerId,
            'limit' => 100,
            'expand' => ['data.charge.dispute'],
        ]);

        return $this->statusesFromInvoices($page->data ?? []);
    }

    private function disputeStatus(object $charge): ?string
    {
        $dispute = $charge->dispute ?? null;
        if (is_object($dispute))
        {
            $status = strtolower(trim((string) ($dispute->status ?? '')));

            return $status !== '' ? $status : 'open';
        }

        if (($charge->disputed ?? false) === true)
        {
            return 'open';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return array<string, string>
     */
    private function cachedStatuses(array $cached): array
    {
        $statuses = $cached['statuses'] ?? [];
        if (! is_array($statuses))
        {
            return [];
        }

        $clean = [];
        foreach ($statuses as $id => $status)
        {
            if (is_string($id) && is_string($status) && $status !== '')
            {
                $clean[$id] = $status;
            }
        }

        return $clean;
    }
}
