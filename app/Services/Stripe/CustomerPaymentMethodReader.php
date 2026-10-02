<?php

namespace App\Services\Stripe;

use App\Models\Team;
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

        $secret = trim((string) $team->getSetting('stripe_secret'));
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
     * @param  array<string, mixed>  $cached
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}|null
     */
    private function cachedCard(array $cached): ?array
    {
        $card = $cached['card'] ?? null;

        return is_array($card) ? $this->mapCard(['card' => $card]) : null;
    }
}
