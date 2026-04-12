<?php

namespace App\Enums;

enum RegistrationMode: string
{
    case Free = 'free';
    case Checkout = 'checkout';
    case Gate = 'gate';

    public static function fromConfiguration(): self
    {
        $raw = strtolower((string) config('registration.mode', 'free'));

        return match ($raw)
        {
            'checkout' => self::Checkout,
            'gate' => self::Gate,
            default => self::Free,
        };
    }

    public function requiresBillingCompletion(): bool
    {
        return $this === self::Checkout || $this === self::Gate;
    }
}
