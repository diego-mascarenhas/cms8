<?php

namespace App\Support;

/**
 * Documented JSON shape for {@see \App\Models\Category::$data} when the category belongs to the "contents" module
 * (section categories used by the dynamic content builder and team contents API).
 *
 * Keys are optional unless noted for a given integration.
 *
 * @phpstan-type PageSections array{history_timeline?: bool}
 * @phpstan-type HistorySectionMeta array{heading?: string}
 * @phpstan-type ContentFormVisibility array{
 *     show_title: bool,
 *     show_subtitle: bool,
 *     show_url: bool,
 *     show_main_content: bool,
 *     show_featured: bool,
 *     show_seo: bool,
 *     show_multimedia: bool
 * }
 * @phpstan-type ContentsSectionCategoryDataArray array{
 *     slug?: string,
 *     page_sections?: PageSections,
 *     history?: HistorySectionMeta,
 *     content_ordering?: array<int, array{column: string, direction: string}>,
 *     content_form?: ContentFormVisibility,
 *     content_locales?: list<string>
 * }
 */
final class ContentsSectionCategoryData
{
    public const DEMO_SLUG_OBA_ABOUT = 'oba-about';

    /**
     * Supported locale codes and display labels for the contents form (category picker + tabs).
     *
     * @return array<string, string>
     */
    public static function supportedLocaleLabels(): array
    {
        return ApplicationLocales::labels();
    }

    /**
     * Locales enabled for a section when reading from stored category data.
     * Missing or null {@see Category::$data} `content_locales` means all supported locales (backward compatible).
     *
     * @param  list<string>|null  $stored
     * @return list<string>
     */
    public static function mergeContentLocalesFromStorage(?array $stored): array
    {
        $allowed = array_keys(self::supportedLocaleLabels());
        if ($stored === null || $stored === [])
        {
            return $allowed;
        }

        $stored = ApplicationLocales::normalizeList($stored);
        $ordered = self::orderedLocaleIntersection($allowed, $stored);

        return $ordered !== [] ? $ordered : [ApplicationLocales::DEFAULT];
    }

    /**
     * Locales from the category edit form (checkboxes). Empty selection defaults to Spanish only.
     *
     * @param  list<string>|null  $selected
     * @return list<string>
     */
    public static function mergeContentLocalesFromRequest(?array $selected): array
    {
        $allowed = array_keys(self::supportedLocaleLabels());
        if ($selected === null || $selected === [])
        {
            return [ApplicationLocales::DEFAULT];
        }

        $selected = ApplicationLocales::normalizeList($selected);
        $ordered = self::orderedLocaleIntersection($allowed, $selected);

        return $ordered !== [] ? $ordered : [ApplicationLocales::DEFAULT];
    }

    /**
     * @param  list<string>  $canonicalOrder
     * @param  list<string>  $selected
     * @return list<string>
     */
    private static function orderedLocaleIntersection(array $canonicalOrder, array $selected): array
    {
        $picked = array_values(array_filter($selected, function ($code) use ($canonicalOrder): bool
        {
            return is_string($code) && in_array($code, $canonicalOrder, true);
        }));

        $out = [];
        foreach ($canonicalOrder as $code)
        {
            if (in_array($code, $picked, true))
            {
                $out[] = $code;
            }
        }

        return $out;
    }

    /**
     * Default visibility for standard fields on the contents create/edit form.
     * Missing keys in stored category data are treated as true (backward compatible).
     *
     * @return ContentFormVisibility
     */
    public static function defaultContentFormVisibility(): array
    {
        return [
            'show_title' => true,
            'show_subtitle' => true,
            'show_url' => true,
            'show_main_content' => true,
            'show_featured' => true,
            'show_seo' => true,
            'show_multimedia' => true,
        ];
    }

    /**
     * Merge stored `data.content_form` with defaults. Unknown keys are dropped.
     *
     * @param  array<string, mixed>|null  $stored
     * @return ContentFormVisibility
     */
    public static function mergeContentFormVisibility(?array $stored): array
    {
        $defaults = self::defaultContentFormVisibility();
        $filtered = array_intersect_key(is_array($stored) ? $stored : [], $defaults);

        return array_merge($defaults, $filtered);
    }

    /**
     * Demo / OBA "Acerca de" section: timeline + builder flags for local and seeded environments.
     *
     * @return ContentsSectionCategoryDataArray
     */
    public static function obaAboutSection(): array
    {
        return [
            'slug' => self::DEMO_SLUG_OBA_ABOUT,
            'page_sections' => [
                'history_timeline' => true,
            ],
            'history' => [
                'heading' => 'CONOCÉ NUESTRA HISTORIA',
            ],
        ];
    }
}
