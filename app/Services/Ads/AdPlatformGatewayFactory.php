<?php

namespace App\Services\Ads;

use App\Contracts\AdPlatformGateway;
use App\Enums\AdPlatform;
use InvalidArgumentException;

class AdPlatformGatewayFactory
{
    public function make(AdPlatform $platform): AdPlatformGateway
    {
        return match ($platform)
        {
            AdPlatform::GoogleAds => app(GoogleAdsGateway::class),
            AdPlatform::Meta => app(MetaAdsGateway::class),
            AdPlatform::LinkedIn => app(LinkedInAdsGateway::class),
            AdPlatform::TikTok => app(TikTokAdsGateway::class),
            AdPlatform::X => app(XAdsGateway::class),
        };
    }

    /**
     * Gateways for every platform that is enabled and has server credentials configured.
     *
     * @return array<string, AdPlatformGateway>
     */
    public function configured(): array
    {
        $gateways = [];

        foreach (AdPlatform::cases() as $platform)
        {
            if (! $platform->isEnabled())
            {
                continue;
            }

            $gateway = $this->make($platform);
            if ($gateway->isConfigured())
            {
                $gateways[$platform->value] = $gateway;
            }
        }

        return $gateways;
    }

    public function makeFromValue(string $platform): AdPlatformGateway
    {
        $enum = AdPlatform::tryFrom($platform);

        if ($enum === null)
        {
            throw new InvalidArgumentException("Unknown ad platform: {$platform}");
        }

        return $this->make($enum);
    }
}
