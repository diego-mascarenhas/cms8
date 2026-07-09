<?php

namespace App\Enums;

enum AdPlatform: string
{
    case GoogleAds = 'google_ads';
    case Meta = 'meta';
    case LinkedIn = 'linkedin';
    case TikTok = 'tiktok';
    case X = 'x';

    public function label(): string
    {
        return match ($this)
        {
            self::GoogleAds => 'Google Ads',
            self::Meta => 'Meta (Facebook / Instagram)',
            self::LinkedIn => 'LinkedIn',
            self::TikTok => 'TikTok',
            self::X => 'X (Twitter)',
        };
    }

    public function icon(): string
    {
        return match ($this)
        {
            self::GoogleAds => 'ti ti-brand-google',
            self::Meta => 'ti ti-brand-meta',
            self::LinkedIn => 'ti ti-brand-linkedin',
            self::TikTok => 'ti ti-brand-tiktok',
            self::X => 'ti ti-brand-twitter',
        };
    }

    public function color(): string
    {
        return match ($this)
        {
            self::GoogleAds => '#4285F4',
            self::Meta => '#0668E1',
            self::LinkedIn => '#0A66C2',
            self::TikTok => '#000000',
            self::X => '#000000',
        };
    }

    /**
     * Whether the platform integration is available for use.
     * Platforms with restricted API access can be gated behind feature flags.
     */
    public function isEnabled(): bool
    {
        return match ($this)
        {
            self::X => (bool) config('services.x_ads.enabled', false),
            default => true,
        };
    }
}
