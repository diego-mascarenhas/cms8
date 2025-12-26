<?php

namespace App\Enums;

enum EmailPlan: string
{
    case FREE = 'free';
    case BASIC = 'basic';
    case FOUNDATION = 'foundation';
    case SCALE = 'scale';

    public function getDisplayName(): string
    {
        return match ($this)
        {
            self::FREE => 'Free',
            self::BASIC => 'Basic',
            self::FOUNDATION => 'Foundation',
            self::SCALE => 'Scale',
        };
    }

    public function getDescription(): string
    {
        return match ($this)
        {
            self::FREE => 'Plan gratuito con límites básicos',
            self::BASIC => 'Ideal para comenzar',
            self::FOUNDATION => 'Para empresas en crecimiento',
            self::SCALE => 'Para grandes empresas',
        };
    }

    public function getMonthlyLimit(): int
    {
        return match ($this)
        {
            self::FREE => 2000,
            self::BASIC => 10000,
            self::FOUNDATION => 50000,
            self::SCALE => 100000,
        };
    }

    public function getDailyLimit(): ?int
    {
        return match ($this)
        {
            self::FREE => 100,
            self::BASIC => 500,
            self::FOUNDATION => 2000,
            self::SCALE => null, // Sin límite diario
        };
    }

    public function getContactLimit(): int
    {
        return match ($this)
        {
            self::FREE => 100,
            self::BASIC => 3000,
            self::FOUNDATION => 20000,
            self::SCALE => 50000,
        };
    }

    public static function getAll(): array
    {
        return [
            self::FREE,
            self::BASIC,
            self::FOUNDATION,
            self::SCALE,
        ];
    }

    /**
     * Get the Stripe product ID for this plan
     */
    public function getStripeProductId(): ?string
    {
        return match ($this)
        {
            self::FREE => null, // FREE plan has no Stripe product
            self::BASIC => 'prod_TRiCHCP6QiGK9n',
            self::FOUNDATION => 'prod_TRiDcPW8PSKYkq',
            self::SCALE => 'prod_TRiDBLKIOlK0pL',
        };
    }

    /**
     * Get the Stripe price ID for this plan (monthly recurring)
     */
    public function getStripePriceId(): ?string
    {
        return match ($this)
        {
            self::FREE => null, // FREE plan has no Stripe price
            self::BASIC => env('STRIPE_MAILER_BASIC', 'price_1SUolyRwN51ygFdec574kfHt'),
            self::FOUNDATION => env('STRIPE_MAILER_FOUNDATION', 'price_1SUomeRwN51ygFdehZBo2SXd'),
            self::SCALE => env('STRIPE_MAILER_SCALE', 'price_1SUon4RwN51ygFdeu3gm5bkR'),
        };
    }

    /**
     * Check if this plan is paid (requires Stripe subscription)
     */
    public function isPaid(): bool
    {
        return $this !== self::FREE;
    }

    public function getConfig(): array
    {
        return [
            'name' => $this->getDisplayName(),
            'description' => $this->getDescription(),
            'monthly_limit' => $this->getMonthlyLimit(),
            'daily_limit' => $this->getDailyLimit(),
            'contact_limit' => $this->getContactLimit(),
        ];
    }
}
