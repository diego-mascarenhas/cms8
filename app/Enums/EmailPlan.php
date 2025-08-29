<?php

namespace App\Enums;

enum EmailPlan: string
{
    case BASIC = 'basic';
    case FOUNDATION = 'foundation';
    case SCALE = 'scale';

    public function getDisplayName(): string
    {
        return match ($this)
        {
            self::BASIC => 'Basic',
            self::FOUNDATION => 'Foundation',
            self::SCALE => 'Scale',
        };
    }

    public function getDescription(): string
    {
        return match ($this)
        {
            self::BASIC => 'Ideal para comenzar',
            self::FOUNDATION => 'Para empresas en crecimiento',
            self::SCALE => 'Para grandes empresas',
        };
    }

    public function getMonthlyLimit(): int
    {
        return match ($this)
        {
            self::BASIC => 10000,
            self::FOUNDATION => 50000,
            self::SCALE => 100000,
        };
    }

    public function getDailyLimit(): ?int
    {
        return match ($this)
        {
            self::BASIC => 500,
            self::FOUNDATION => 2000,
            self::SCALE => null, // Sin límite diario
        };
    }

    public function getContactLimit(): int
    {
        return match ($this)
        {
            self::BASIC => 3000,
            self::FOUNDATION => 20000,
            self::SCALE => 50000,
        };
    }

    public static function getAll(): array
    {
        return [
            self::BASIC,
            self::FOUNDATION,
            self::SCALE,
        ];
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
