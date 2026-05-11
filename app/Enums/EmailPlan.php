<?php

namespace App\Enums;

use App\Models\SubscriptionProduct;

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
     * Resolve a paid mailer plan from a Stripe product ID (subscription_products / enum mapping).
     */
    public static function tryFromStripeProductId(string $productId): ?self
    {
        foreach (self::getAll() as $plan)
        {
            if ($plan->getStripeProductId() === $productId)
            {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Get the Stripe product ID for this plan (from subscription_products).
     */
    public function getStripeProductId(): ?string
    {
        if ($this === self::FREE)
        {
            return null;
        }

        return SubscriptionProduct::getMailerProductId($this->value);
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

        return SubscriptionProduct::getMailerPriceId($this->value);
    }

    /**
     * Check if this plan is paid (requires Stripe subscription)
     */
    public function isPaid(): bool
    {
        return $this !== self::FREE;
    }

    /**
     * Get EmailPlan from Stripe price ID (from subscription_products, fallback to Stripe API).
     */
    public static function fromStripePriceId(?string $priceId): self
    {
        if (! $priceId)
        {
            return self::FREE;
        }

        $product = SubscriptionProduct::findByStripePrice($priceId);
        if ($product && $product->category === 'mailer' && $product->plan)
        {
            $plan = self::tryFrom($product->plan);

            return $plan ?? self::FREE;
        }

        return self::fromStripeProductId($priceId);
    }

    /**
     * Get EmailPlan from Stripe price ID by resolving product via Stripe API (fallback).
     */
    private static function fromStripeProductId(string $priceId): self
    {
        try
        {
            $product = SubscriptionProduct::active()
                ->where('category', 'mailer')
                ->where('stripe_price', $priceId)
                ->first();
            if ($product && $product->plan)
            {
                $plan = self::tryFrom($product->plan);

                return $plan ?? self::FREE;
            }

            \Stripe\Stripe::setApiKey(\App\Services\StripeAccountResolver::secretForCategory('mailer'));
            $price = \Stripe\Price::retrieve($priceId);
            if ($price && $price->product)
            {
                $productId = is_string($price->product) ? $price->product : $price->product->id;
                $product = SubscriptionProduct::active()
                    ->where('category', 'mailer')
                    ->where('stripe_product', $productId)
                    ->first();
                if ($product && $product->plan)
                {
                    $plan = self::tryFrom($product->plan);

                    return $plan ?? self::FREE;
                }
            }
        } catch (\Exception $e)
        {
            \Log::warning("Could not determine plan from price ID {$priceId}: ".$e->getMessage());
        }

        return self::FREE;
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
