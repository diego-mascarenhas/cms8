<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentResolveAdministrativeTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_first_non_empty_translation_using_locale_priority(): void
    {
        $section = Category::factory()->create([
            'data' => [
                'content_locales' => ['en', 'es'],
            ],
        ]);

        $content = new Content([
            'title' => [
                'en' => '',
                'es' => '  INICIO  ',
            ],
        ]);
        $content->setRelation('sectionCategory', $section);

        $this->assertSame('INICIO', $content->resolveAdministrativeTitle());
    }

    public function test_returns_null_when_all_title_values_empty(): void
    {
        $content = new Content([
            'title' => [
                'es' => '',
                'en' => '   ',
            ],
        ]);

        $this->assertNull($content->resolveAdministrativeTitle());
    }
}
