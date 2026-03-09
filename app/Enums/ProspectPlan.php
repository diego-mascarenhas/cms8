<?php

namespace App\Enums;

use App\Models\SubscriptionProduct;

enum ProspectPlan: string
{
    case FREE = 'free';
    case BASIC = 'basic';
    case GROWTH = 'growth';

    public function getDisplayName(): string
    {
        return match ($this)
        {
            self::FREE => 'Free',
            self::BASIC => 'Basic',
            self::GROWTH => 'Growth',
        };
    }

    public function getDescription(): string
    {
        return match ($this)
        {
            self::FREE => 'Sin créditos de prospectos incluidos',
            self::BASIC => 'Ideal para comenzar a importar prospectos',
            self::GROWTH => 'Para equipos que importan muchos prospectos',
        };
    }

    public function getMonthlyCredits(): int
    {
        return match ($this)
        {
            self::FREE => 0,
            self::BASIC => 50,
            self::GROWTH => 200,
        };
    }

    /**
     * Get the Stripe price ID for this plan (from subscription_products).
     */
    public function getStripePriceId(): ?string
    {
        if ($this === self::FREE)
        {
            return null;
        }

        return SubscriptionProduct::getProspectRecurringPriceId($this->value);
    }

    public function isPaid(): bool
    {
        return $this !== self::FREE;
    }

    public static function getAll(): array
    {
        return [
            self::FREE,
            self::BASIC,
            self::GROWTH,
        ];
    }

    /**
     * Get ProspectPlan from Stripe price ID (from subscription_products).
     */
    public static function fromStripePriceId(?string $priceId): self
    {
        if (! $priceId)
        {
            return self::FREE;
        }

        $product = SubscriptionProduct::findByStripePrice($priceId);
        if ($product && $product->category === 'prospecting' && $product->plan)
        {
            $plan = self::tryFrom($product->plan);

            return $plan ?? self::FREE;
        }

        return self::FREE;
    }

    public function getConfig(): array
    {
        return [
            'name' => $this->getDisplayName(),
            'description' => $this->getDescription(),
            'monthly_credits' => $this->getMonthlyCredits(),
        ];
    }
}
