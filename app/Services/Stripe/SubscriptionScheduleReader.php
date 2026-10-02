<?php

namespace App\Services\Stripe;

use App\Models\ServiceSync;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Stripe\StripeClient;
use Throwable;

class SubscriptionScheduleReader
{
    /**
     * Subscription schedules that have not started yet. Stripe does not return them from the subscription list.
     *
     * @return Collection<int, ServiceSync>
     */
    public function upcomingForCustomer(?Team $team, string $customerId): Collection
    {
        $customerId = trim($customerId);
        if (! $team || ! str_starts_with($customerId, 'cus_'))
        {
            return collect();
        }

        $secret = trim((string) $team->getSetting('stripe_secret'));
        if ($secret === '')
        {
            return collect();
        }

        $cacheKey = 'client-subscription-schedules:'.$team->id.':'.$customerId;
        $cached = Cache::get($cacheKey);
        if (is_array($cached))
        {
            return $this->hydrate($cached);
        }

        try
        {
            $rows = $this->fetchRows($secret, $customerId);
        } catch (Throwable $exception)
        {
            report($exception);

            return collect();
        }

        Cache::put($cacheKey, $rows, now()->addMinutes(10));

        return $this->hydrate($rows);
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array<string, mixed>|null
     */
    public function mapNotStarted(array $schedule): ?array
    {
        if (($schedule['status'] ?? '') !== 'not_started')
        {
            return null;
        }

        $phase = $schedule['phases'][0] ?? null;
        if (! is_array($phase))
        {
            return null;
        }

        $item = $phase['items'][0] ?? [];
        if (! is_array($item))
        {
            $item = [];
        }

        $price = is_array($item['price'] ?? null) ? $item['price'] : [];
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unitAmount = $this->normalizeAmount(
            isset($price['unit_amount_decimal']) ? (string) $price['unit_amount_decimal'] : null,
            isset($price['unit_amount']) ? (int) $price['unit_amount'] : null,
        );
        $description = trim((string) ($phase['description'] ?? ''));
        $productName = data_get($price, 'product.name');
        $customer = $schedule['customer'] ?? null;
        $start = isset($phase['start_date'])
            ? Carbon::createFromTimestampUTC((int) $phase['start_date'])
            : null;

        return [
            'provider' => 'stripe',
            'stripe_id' => (string) ($schedule['id'] ?? ''),
            'type' => 'sell',
            'customer_id' => is_string($customer) ? $customer : data_get($customer, 'id'),
            'status' => 'not_started',
            'collection_method' => $phase['collection_method']
                ?? data_get($schedule, 'default_settings.collection_method')
                ?? 'charge_automatically',
            'plan_name' => $price['nickname'] ?? (is_string($productName) ? $productName : null) ?? ($description !== '' ? $description : null),
            'plan_interval' => data_get($price, 'recurring.interval'),
            'plan_interval_count' => data_get($price, 'recurring.interval_count') ?? 1,
            'quantity' => $quantity,
            'price_currency' => strtoupper((string) ($price['currency'] ?? '')),
            'unit_amount' => $unitAmount,
            'amount_total' => $unitAmount !== null ? $unitAmount * $quantity : null,
            'current_period_end' => $start?->toIso8601String(),
            'raw_payload' => [
                'id' => $schedule['id'] ?? null,
                'description' => $description,
                'status' => 'not_started',
                'discounts' => is_array($phase['discounts'] ?? null) ? $phase['discounts'] : [],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $secret, string $customerId): array
    {
        $stripe = new StripeClient($secret);
        $collection = $stripe->subscriptionSchedules->all([
            'customer' => $customerId,
            'limit' => 100,
            'expand' => ['data.phases.items.price', 'data.phases.discounts.coupon'],
        ]);

        $rows = [];
        foreach ($collection->autoPagingIterator() as $schedule)
        {
            $mapped = $this->mapNotStarted($schedule->toArray());
            if ($mapped !== null && $mapped['stripe_id'] !== '')
            {
                $rows[] = $mapped;
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, ServiceSync>
     */
    private function hydrate(array $rows): Collection
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): ServiceSync => new ServiceSync($row))
            ->values();
    }

    private function normalizeAmount(?string $decimalAmount, ?int $integerAmount): ?float
    {
        if ($decimalAmount !== null && $decimalAmount !== '')
        {
            return ((float) $decimalAmount) / 100;
        }

        if ($integerAmount !== null)
        {
            return $integerAmount / 100;
        }

        return null;
    }
}
