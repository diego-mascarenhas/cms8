<?php

namespace App\Support;

class HumanoPricingCatalog
{
    public const ASSISTANT = 'assistant';

    public const PLATFORM = 'platform';

    public const MAILER = 'mailer';

    public const SHOP = 'shop';

    public const ADS = 'ads';

    public const PROJECTS = 'projects';

    public const AFFILIATES = 'affiliates';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ASSISTANT,
            self::PLATFORM,
            self::MAILER,
            self::SHOP,
            self::ADS,
            self::PROJECTS,
            self::AFFILIATES,
        ];
    }

    public static function normalize(?string $catalog): ?string
    {
        $catalog = strtolower(trim((string) $catalog));

        return in_array($catalog, self::all(), true) ? $catalog : null;
    }
}
