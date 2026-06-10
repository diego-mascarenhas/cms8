<?php

namespace App\Helpers;

class SupportWhatsAppHelper
{
    private const DEFAULT_PHONE = '34624159557';

    public static function phoneDigits(): string
    {
        foreach ([
            config('app.whatsapp_support'),
            config('app.wapify_whatsapp_phone'),
            self::DEFAULT_PHONE,
        ] as $candidate)
        {
            $digits = preg_replace('/\D/', '', (string) $candidate);
            if ($digits !== '')
            {
                return $digits;
            }
        }

        return '';
    }

    public static function telUrl(): ?string
    {
        $phone = self::phoneDigits();
        if ($phone === '')
        {
            return null;
        }

        return 'tel:+'.$phone;
    }

    public static function phoneDisplay(): ?string
    {
        $phone = self::phoneDigits();
        if ($phone === '')
        {
            return null;
        }

        if (str_starts_with($phone, '34') && strlen($phone) === 11)
        {
            return '+34 '
                .substr($phone, 2, 3).' '
                .substr($phone, 5, 2).' '
                .substr($phone, 7, 2).' '
                .substr($phone, 9, 2);
        }

        return '+'.$phone;
    }

    public static function webUrl(?string $text = null): ?string
    {
        $phone = self::phoneDigits();
        if ($phone === '')
        {
            return null;
        }

        $url = 'https://web.whatsapp.com/send?phone='.$phone;

        $message = $text ?? trim((string) config('app.wapify_whatsapp_text', ''));
        if ($message !== '')
        {
            $url .= '&text='.rawurlencode($message);
        }

        return $url;
    }
}
