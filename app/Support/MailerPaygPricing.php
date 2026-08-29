<?php

namespace App\Support;

class MailerPaygPricing
{
    public static function pricePerEmail(): string
    {
        return trim((string) config('emailer.payg.price_per_email', '0.01')) ?: '0.01';
    }

    public static function currency(): string
    {
        return strtoupper(trim((string) config('emailer.payg.currency', 'EUR')) ?: 'EUR');
    }

    public static function amountToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function overageDueCents(int $overageEmails): int
    {
        if ($overageEmails < 1)
        {
            return 0;
        }

        return $overageEmails * self::amountToCents(self::pricePerEmail());
    }
}
