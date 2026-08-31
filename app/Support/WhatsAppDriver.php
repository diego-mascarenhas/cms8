<?php

namespace App\Support;

use App\Models\Team;

final class WhatsAppDriver
{
    public const LOCAL = 'local';

    public const META = 'meta';

    public const TWILIO = 'twilio';

    public const DIALOG360 = '360dialog';

    public const MESSAGEBIRD = 'messagebird';

    public const SETTING_KEY = 'whatsapp_driver';

    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        return [
            self::LOCAL,
            self::META,
            self::TWILIO,
            self::DIALOG360,
            self::MESSAGEBIRD,
        ];
    }

    /**
     * Drivers that can send today (Baileys + Twilio). The rest are selectable and stored.
     *
     * @return list<string>
     */
    public static function implemented(): array
    {
        return [self::LOCAL, self::TWILIO];
    }

    /**
     * @return array<string, string>
     */
    public static function selectOptions(): array
    {
        return [
            self::LOCAL => __('Baileys (QR)'),
            self::META => __('Meta Cloud API'),
            self::TWILIO => __('Twilio'),
            self::DIALOG360 => __('360dialog'),
            self::MESSAGEBIRD => __('MessageBird'),
        ];
    }

    public static function normalize(?string $value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, self::allowed(), true) ? $value : self::LOCAL;
    }

    public static function forTeam(?Team $team): string
    {
        if ($team === null)
        {
            return self::LOCAL;
        }

        return $team->getWhatsAppDriver();
    }

    public static function isLocal(?Team $team): bool
    {
        return self::forTeam($team) === self::LOCAL;
    }

    public static function isImplemented(?Team $team): bool
    {
        return in_array(self::forTeam($team), self::implemented(), true);
    }
}
