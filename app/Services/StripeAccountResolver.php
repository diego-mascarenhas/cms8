<?php

namespace App\Services;

class StripeAccountResolver
{
    /**
     * Whether this category uses a dedicated key from config/stripe_accounts.php (not Cashier defaults).
     */
    public static function hasDedicatedCredentials(string $category): bool
    {
        $category = trim($category);
        if ($category === '')
        {
            return false;
        }

        $normalized = self::normalizeCategory($category);

        return ! empty(config("stripe_accounts.{$normalized}.secret"));
    }

    /**
     * Return the Stripe secret key for the given category.
     * Empty category uses config('cashier.secret') (default .env STRIPE_SECRET).
     * Otherwise falls back to Cashier when the category has no dedicated credentials.
     */
    public static function secretForCategory(string $category): string
    {
        if (trim($category) === '')
        {
            return (string) config('cashier.secret');
        }

        $secret = config('stripe_accounts.'.self::normalizeCategory($category).'.secret');

        return $secret ?: (string) config('cashier.secret');
    }

    /**
     * Return the Stripe publishable key for the given category.
     * Empty category uses config('cashier.key'). Otherwise falls back to Cashier when no dedicated key.
     */
    public static function keyForCategory(string $category): ?string
    {
        if (trim($category) === '')
        {
            return config('cashier.key');
        }

        $key = config('stripe_accounts.'.self::normalizeCategory($category).'.key');

        return $key ?: config('cashier.key');
    }

    /**
     * Normalize category for config lookup (e.g. 'support' uses same credentials as 'hosting').
     */
    public static function normalizeCategory(string $category): string
    {
        return $category === 'support' ? 'hosting' : $category;
    }
}
