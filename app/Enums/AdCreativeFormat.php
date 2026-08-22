<?php

namespace App\Enums;

enum AdCreativeFormat: string
{
    case Square = 'square';
    case Portrait = 'portrait';
    case Story = 'story';
    case Landscape = 'landscape';
    case Widescreen = 'widescreen';

    public function label(): string
    {
        return match ($this)
        {
            self::Square => 'Cuadrado',
            self::Portrait => 'Vertical',
            self::Story => 'Historia',
            self::Landscape => 'Horizontal',
            self::Widescreen => 'Panorámico',
        };
    }

    public function ratio(): string
    {
        return match ($this)
        {
            self::Square => '1:1',
            self::Portrait => '4:5',
            self::Story => '9:16',
            self::Landscape => '1.91:1',
            self::Widescreen => '16:9',
        };
    }

    public function width(): int
    {
        return match ($this)
        {
            self::Square => 1080,
            self::Portrait => 1080,
            self::Story => 1080,
            self::Landscape => 1200,
            self::Widescreen => 1920,
        };
    }

    public function height(): int
    {
        return match ($this)
        {
            self::Square => 1080,
            self::Portrait => 1350,
            self::Story => 1920,
            self::Landscape => 628,
            self::Widescreen => 1080,
        };
    }

    /**
     * @return array<int, AdPlatform>
     */
    public function platforms(): array
    {
        return match ($this)
        {
            self::Square => [AdPlatform::GoogleAds, AdPlatform::Meta, AdPlatform::LinkedIn, AdPlatform::X],
            self::Portrait => [AdPlatform::Meta],
            self::Story => [AdPlatform::Meta, AdPlatform::TikTok],
            self::Landscape => [AdPlatform::GoogleAds, AdPlatform::Meta, AdPlatform::LinkedIn, AdPlatform::X],
            self::Widescreen => [AdPlatform::GoogleAds, AdPlatform::LinkedIn, AdPlatform::TikTok, AdPlatform::X],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toLookup(): array
    {
        return [
            'key' => $this->value,
            'label' => $this->label(),
            'ratio' => $this->ratio(),
            'width' => $this->width(),
            'height' => $this->height(),
            'platforms' => collect($this->platforms())
                ->filter(fn (AdPlatform $platform) => $platform->isEnabled())
                ->map(fn (AdPlatform $platform) => [
                    'key' => $platform->value,
                    'label' => $platform->label(),
                    'color' => $platform->color(),
                ])
                ->values()
                ->all(),
        ];
    }
}
