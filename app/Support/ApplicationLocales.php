<?php

namespace App\Support;

final class ApplicationLocales
{
    public const DEFAULT = 'es_ES';

    public const ARGENTINA = 'es_AR';

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return [
            self::DEFAULT,
            self::ARGENTINA,
            'en',
            'it',
            'pt',
            'fr',
            'de',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::DEFAULT => 'Español (España)',
            self::ARGENTINA => 'Español (Argentina)',
            'en' => 'English',
            'it' => 'Italiano',
            'pt' => 'Português',
            'fr' => 'Français',
            'de' => 'Deutsch',
        ];
    }

    public static function normalize(?string $locale): string
    {
        $locale = is_string($locale) ? trim($locale) : '';

        return match ($locale)
        {
            '', 'es', 'es-ES' => self::DEFAULT,
            'es-AR' => self::ARGENTINA,
            default => in_array($locale, self::supported(), true) ? $locale : self::DEFAULT,
        };
    }

    public static function isSupported(?string $locale): bool
    {
        return in_array(self::normalize($locale), self::supported(), true);
    }

    /**
     * @param  list<string>|null  $locales
     * @return list<string>
     */
    public static function normalizeList(?array $locales): array
    {
        if ($locales === null || $locales === [])
        {
            return [];
        }

        $normalized = [];

        foreach ($locales as $locale)
        {
            $code = self::normalize(is_string($locale) ? $locale : null);

            if (! in_array($code, $normalized, true))
            {
                $normalized[] = $code;
            }
        }

        return $normalized;
    }

    /**
     * Ordered locale keys when reading legacy translatable content stored per locale.
     *
     * @return list<string>
     */
    public static function contentTranslationCandidates(?string $locale = null): array
    {
        $locale = self::normalize($locale);

        return match ($locale)
        {
            self::ARGENTINA => [self::ARGENTINA, self::DEFAULT, 'es'],
            self::DEFAULT => [self::DEFAULT, 'es'],
            default => array_values(array_unique([$locale, self::DEFAULT, 'es'])),
        };
    }

    /**
     * Locale key accepted by Vuexy TemplateCustomizer (en, es, fr, …).
     */
    public static function templateCustomizerLang(?string $locale = null): string
    {
        $locale = self::normalize($locale ?? app()->getLocale());

        return match ($locale)
        {
            self::DEFAULT, self::ARGENTINA => 'es',
            'en', 'fr', 'de', 'it', 'pt', 'ar' => $locale,
            default => 'es',
        };
    }
}
