<?php

namespace App\Services\Stripe;

use App\Models\Team;
use App\Services\StripeAccountResolver;
use Illuminate\Support\Facades\Cache;
use Stripe\StripeClient;
use Throwable;

class CustomerPaymentMethodReader
{
    /**
     * Public card fields for the customer's default payment method.
     *
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}|null
     */
    public function forCustomer(?Team $team, string $customerId): ?array
    {
        $customerId = trim($customerId);
        if (! $team || ! str_starts_with($customerId, 'cus_'))
        {
            return null;
        }

        $secret = trim(StripeAccountResolver::secretForCategory('hosting'));
        if ($secret === '')
        {
            return null;
        }

        $cacheKey = 'client-payment-method:'.$team->id.':'.$customerId;
        $cached = Cache::get($cacheKey);
        if (is_array($cached))
        {
            return $this->cachedCard($cached);
        }

        try
        {
            $card = $this->fetchCard($secret, $customerId);
        } catch (Throwable $exception)
        {
            report($exception);

            return null;
        }

        Cache::put($cacheKey, ['card' => $card], now()->addMinutes(10));

        return $card;
    }

    /**
     * Saved cards for the customer. The invoice default comes first and is marked.
     *
     * @return list<array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, default: bool}>
     */
    public function cardsForCustomer(?Team $team, string $customerId): array
    {
        $customerId = trim($customerId);
        if (! $team || ! str_starts_with($customerId, 'cus_'))
        {
            return [];
        }

        $secret = trim(StripeAccountResolver::secretForCategory('hosting'));
        if ($secret === '')
        {
            return [];
        }

        $cacheKey = $this->cardsCacheKey($team->id, $customerId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached))
        {
            return $this->cachedCards($cached);
        }

        try
        {
            $cards = $this->fetchCards($secret, $customerId);
        } catch (Throwable $exception)
        {
            report($exception);

            return [];
        }

        Cache::put($cacheKey, ['cards' => $cards], now()->addMinutes(10));

        return $cards;
    }

    public function forget(int $teamId, string $customerId): void
    {
        $customerId = trim($customerId);
        Cache::forget('client-payment-method:'.$teamId.':'.$customerId);
        Cache::forget($this->cardsCacheKey($teamId, $customerId));
    }

    /**
     * @param  list<array<string, mixed>>  $methods
     * @return list<array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, default: bool}>
     */
    public function listedCards(array $methods, string $defaultId): array
    {
        $cards = [];

        foreach ($methods as $method)
        {
            $mapped = $this->mapCard($method);
            $id = trim((string) ($method['id'] ?? ''));
            if ($mapped === null || $id === '')
            {
                continue;
            }

            $cards[] = [
                'id' => $id,
                'brand' => $mapped['brand'],
                'last4' => $mapped['last4'],
                'exp_month' => $mapped['exp_month'],
                'exp_year' => $mapped['exp_year'],
                'default' => $defaultId !== '' && $id === $defaultId,
            ];
        }

        if ($cards !== [] && ! in_array(true, array_column($cards, 'default'), true))
        {
            $cards[0]['default'] = true;
        }

        usort($cards, fn (array $left, array $right): int => $right['default'] <=> $left['default']);

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $paymentMethod
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}|null
     */
    public function mapCard(array $paymentMethod): ?array
    {
        $card = $paymentMethod['card'] ?? null;
        if (! is_array($card))
        {
            return null;
        }

        $last4 = trim((string) ($card['last4'] ?? ''));
        if ($last4 === '')
        {
            return null;
        }

        return [
            'brand' => trim((string) ($card['brand'] ?? '')),
            'last4' => $last4,
            'exp_month' => (int) ($card['exp_month'] ?? 0),
            'exp_year' => (int) ($card['exp_year'] ?? 0),
        ];
    }

    /**
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}|null
     */
    private function fetchCard(string $secret, string $customerId): ?array
    {
        $stripe = new StripeClient($secret);
        $customer = $stripe->customers->retrieve($customerId, [
            'expand' => ['invoice_settings.default_payment_method'],
        ]);

        $default = $customer->invoice_settings->default_payment_method ?? null;
        if (is_object($default) && method_exists($default, 'toArray'))
        {
            $mapped = $this->mapCard($default->toArray());
            if ($mapped !== null)
            {
                return $mapped;
            }
        }

        $methods = $stripe->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
            'limit' => 1,
        ]);

        $first = $methods->data[0] ?? null;
        if (is_object($first) && method_exists($first, 'toArray'))
        {
            return $this->mapCard($first->toArray());
        }

        return null;
    }

    /**
     * @return list<array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, default: bool}>
     */
    private function fetchCards(string $secret, string $customerId): array
    {
        $stripe = new StripeClient($secret);
        $customer = $stripe->customers->retrieve($customerId, [
            'expand' => ['invoice_settings.default_payment_method'],
        ]);
        $default = $customer->invoice_settings->default_payment_method ?? null;
        $defaultId = '';

        if (is_object($default))
        {
            $defaultId = trim((string) ($default->id ?? ''));
        } elseif (is_string($default))
        {
            $defaultId = trim($default);
        }

        $methods = $stripe->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
            'limit' => 10,
        ]);
        $rows = [];

        foreach ($methods->data as $method)
        {
            if (is_object($method) && method_exists($method, 'toArray'))
            {
                $rows[] = $method->toArray();
            }
        }

        return $this->listedCards($rows, $defaultId);
    }

    private function cardsCacheKey(int $teamId, string $customerId): string
    {
        return 'client-payment-methods:'.$teamId.':'.$customerId;
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return list<array{id: string, brand: string, last4: string, exp_month: int, exp_year: int, default: bool}>
     */
    private function cachedCards(array $cached): array
    {
        $cards = $cached['cards'] ?? null;
        if (! is_array($cards))
        {
            return [];
        }

        $rows = [];

        foreach ($cards as $card)
        {
            if (! is_array($card))
            {
                continue;
            }

            $id = trim((string) ($card['id'] ?? ''));
            $last4 = trim((string) ($card['last4'] ?? ''));
            if ($id === '' || $last4 === '')
            {
                continue;
            }

            $rows[] = [
                'id' => $id,
                'brand' => trim((string) ($card['brand'] ?? '')),
                'last4' => $last4,
                'exp_month' => (int) ($card['exp_month'] ?? 0),
                'exp_year' => (int) ($card['exp_year'] ?? 0),
                'default' => (bool) ($card['default'] ?? false),
            ];
        }

        if ($rows !== [] && ! in_array(true, array_column($rows, 'default'), true))
        {
            $rows[0]['default'] = true;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}|null
     */
    private function cachedCard(array $cached): ?array
    {
        $card = $cached['card'] ?? null;

        return is_array($card) ? $this->mapCard(['card' => $card]) : null;
    }
}
