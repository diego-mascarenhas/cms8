<?php

namespace App\Services;

class StripeAccountResolver
{
    /**
     * Return the Stripe secret key for the given category.
     * Falls back to config('cashier.secret') when the category has no dedicated credentials.
     */
    public static function secretForCategory(string $category): string
    {
        $secret = config("stripe_accounts.{$category}.secret");

        return $secret ?: (string) config('cashier.secret');
    }

    /**
     * Return the Stripe publishable key for the given category.
     * Falls back to config('cashier.key') when the category has no dedicated key.
     */
    public static function keyForCategory(string $category): ?string
    {
        $key = config("stripe_accounts.{$category}.key");

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
