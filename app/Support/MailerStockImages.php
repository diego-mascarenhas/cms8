<?php

namespace App\Support;

use Illuminate\Support\Str;

final class MailerStockImages
{
    public const NEWSLETTER = 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&h=480&q=80';

    public const WELCOME = 'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1200&h=480&q=80';

    public const PROMO = 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?auto=format&fit=crop&w=1200&h=480&q=80';

    public const EVENT = 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=1200&h=480&q=80';

    public const REMINDER = 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=1200&h=480&q=80';

    public static function urlForTopic(string $topic): string
    {
        $haystack = Str::lower($topic);

        if (Str::contains($haystack, ['promo', 'oferta', 'descuento', 'verano', 'sale']))
        {
            return self::PROMO;
        }

        if (Str::contains($haystack, ['bienvenida', 'welcome', 'onboarding', 'nuevo']))
        {
            return self::WELCOME;
        }

        if (Str::contains($haystack, ['evento', 'webinar', 'invit', 'event']))
        {
            return self::EVENT;
        }

        if (Str::contains($haystack, ['recordatorio', 'reminder', 'turno', 'vencimiento']))
        {
            return self::REMINDER;
        }

        return self::NEWSLETTER;
    }

    public static function heroHtml(string $src, string $alt = ''): string
    {
        return '<p><img src="'.e($src).'" alt="'.e($alt).'" width="600" style="width:100%;max-width:600px;height:auto;border:0;display:block;"></p>';
    }

    public static function ensureHero(string $html, string $topic): string
    {
        if (stripos($html, '<img') !== false)
        {
            return $html;
        }

        return self::heroHtml(self::urlForTopic($topic), 'Imagen de cabecera').$html;
    }
}
