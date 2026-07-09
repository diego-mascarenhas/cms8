<?php

namespace App\Support;

use App\Enums\AdPlatform;
use App\Models\Team;

/**
 * Resolves ad platform API credentials for a team.
 *
 * Credentials are configured per team in team settings (encrypted), with an
 * optional fallback to the global config/services.php values for shared apps.
 */
class AdPlatformCredentials
{
    /**
     * Team setting keys that hold secrets and must be stored encrypted.
     *
     * @var array<int, string>
     */
    public const ENCRYPTED_KEYS = [
        'paid_ads_google_client_secret',
        'paid_ads_google_developer_token',
        'paid_ads_meta_app_secret',
        'paid_ads_linkedin_client_secret',
        'paid_ads_tiktok_app_secret',
        'paid_ads_x_client_secret',
    ];

    /**
     * Map of platform -> field -> [team setting key, config fallback key].
     *
     * @return array<string, array{setting: string, config: ?string}>
     */
    public static function fieldMap(AdPlatform $platform): array
    {
        return match ($platform)
        {
            AdPlatform::GoogleAds => [
                'client_id' => ['setting' => 'paid_ads_google_client_id', 'config' => 'services.google_ads.client_id'],
                'client_secret' => ['setting' => 'paid_ads_google_client_secret', 'config' => 'services.google_ads.client_secret'],
                'developer_token' => ['setting' => 'paid_ads_google_developer_token', 'config' => 'services.google_ads.developer_token'],
                'login_customer_id' => ['setting' => 'paid_ads_google_login_customer_id', 'config' => 'services.google_ads.login_customer_id'],
            ],
            AdPlatform::Meta => [
                'client_id' => ['setting' => 'paid_ads_meta_app_id', 'config' => 'services.meta_ads.app_id'],
                'client_secret' => ['setting' => 'paid_ads_meta_app_secret', 'config' => 'services.meta_ads.app_secret'],
            ],
            AdPlatform::LinkedIn => [
                'client_id' => ['setting' => 'paid_ads_linkedin_client_id', 'config' => 'services.linkedin_ads.client_id'],
                'client_secret' => ['setting' => 'paid_ads_linkedin_client_secret', 'config' => 'services.linkedin_ads.client_secret'],
            ],
            AdPlatform::TikTok => [
                'client_id' => ['setting' => 'paid_ads_tiktok_app_id', 'config' => 'services.tiktok_ads.app_id'],
                'client_secret' => ['setting' => 'paid_ads_tiktok_app_secret', 'config' => 'services.tiktok_ads.app_secret'],
            ],
            AdPlatform::X => [
                'client_id' => ['setting' => 'paid_ads_x_client_id', 'config' => 'services.x_ads.client_id'],
                'client_secret' => ['setting' => 'paid_ads_x_client_secret', 'config' => 'services.x_ads.client_secret'],
            ],
        };
    }

    /**
     * Resolve a single credential field for a team (team setting first, then config fallback).
     */
    public static function get(AdPlatform $platform, string $field, ?Team $team): ?string
    {
        $map = self::fieldMap($platform);

        if (! isset($map[$field]))
        {
            return null;
        }

        $definition = $map[$field];

        if ($team !== null)
        {
            $value = $team->getSetting($definition['setting']);
            if (! empty($value))
            {
                return (string) $value;
            }
        }

        if ($definition['config'] !== null)
        {
            $fallback = config($definition['config']);
            if (! empty($fallback))
            {
                return (string) $fallback;
            }
        }

        return null;
    }
}
