<?php

namespace App\Enums;

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

    public function getStripePriceId(): ?string
    {
        return match ($this)
        {
            self::FREE => null,
            self::BASIC => config('prospects.stripe_basic_price_id'),
            self::GROWTH => config('prospects.stripe_growth_price_id'),
        };
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

    public static function fromStripePriceId(?string $priceId): self
    {
        if (! $priceId)
        {
            return self::FREE;
        }

        $basicPrice = config('prospects.stripe_basic_price_id');
        $growthPrice = config('prospects.stripe_growth_price_id');

        return match ($priceId)
        {
            $basicPrice => self::BASIC,
            $growthPrice => self::GROWTH,
            default => self::FREE,
        };
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
