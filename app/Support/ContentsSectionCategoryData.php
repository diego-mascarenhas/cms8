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
 * @phpstan-type ContentsSectionCategoryDataArray array{
 *     slug?: string,
 *     page_sections?: PageSections,
 *     history?: HistorySectionMeta,
 *     content_ordering?: array<int, array{column: string, direction: string}>
 * }
 */
final class ContentsSectionCategoryData
{
    public const DEMO_SLUG_OBA_ABOUT = 'oba-about';

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
