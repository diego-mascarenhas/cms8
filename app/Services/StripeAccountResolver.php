<?php

namespace App\Services;

class StripeAccountResolver
{
    /**
     * All products share the default Cashier account (STRIPE_SECRET / STRIPE_KEY).
     */
    public static function hasDedicatedCredentials(string $category): bool
    {
        return false;
    }

    /**
     * Return the platform Stripe secret. Category is ignored: one account for every product.
     */
    public static function secretForCategory(string $category): string
    {
        return (string) config('cashier.secret');
    }

    /**
     * Return the platform Stripe publishable key. Category is ignored.
     */
    public static function keyForCategory(string $category): ?string
    {
        return config('cashier.key');
    }

    /**
     * Return the platform webhook signing secret. Category is ignored.
     */
    public static function webhookSecretForCategory(string $category): ?string
    {
        return config('cashier.webhook.secret');
    }

    /**
     * Normalize product category names (e.g. support subscriptions use hosting plans).
     */
    public static function normalizeCategory(string $category): string
    {
        return $category === 'support' ? 'hosting' : $category;
    }
}
