<?php

namespace Tests\Unit;

use App\Support\ApplicationLocales;
use Tests\TestCase;

class ApplicationLocalesTest extends TestCase
{
    public function test_default_locale_is_spain_spanish(): void
    {
        $this->assertSame('es_ES', ApplicationLocales::DEFAULT);
        $this->assertSame('es_ES', config('app.locale'));
    }

    public function test_normalize_maps_legacy_es_to_spain(): void
    {
        $this->assertSame('es_ES', ApplicationLocales::normalize('es'));
        $this->assertSame('es_ES', ApplicationLocales::normalize('es-ES'));
        $this->assertSame('es_AR', ApplicationLocales::normalize('es-AR'));
        $this->assertSame('es_AR', ApplicationLocales::normalize('es_AR'));
    }

    public function test_slash_landing_copy_defaults_to_spain_spanish(): void
    {
        app()->setLocale(ApplicationLocales::DEFAULT);

        $this->assertSame('Conoce cada plan en acción', __('slash_landing.stories.title'));
        $this->assertSame('Todo lo que necesitas para gestionar tu negocio', __('slash_landing.benefits.title'));
        $this->assertSame('Completa cualquier tarea en pocos clics', __('slash_landing.tools.title'));
    }

    public function test_spain_and_argentina_dictionaries_differ(): void
    {
        $this->assertSame(
            'Compara módulos y capacidades. Los precios y el checkout están en la página de planes.',
            __('humano_pricing.landing_plans_subtitle', [], 'es_ES'),
        );

        $this->assertSame(
            'Compará módulos y capacidades. Los precios y el checkout están en la página de planes.',
            __('humano_pricing.landing_plans_subtitle', [], 'es_AR'),
        );
    }

    public function test_content_translation_candidates_keep_legacy_es_fallback(): void
    {
        $this->assertSame(['es_ES', 'es'], ApplicationLocales::contentTranslationCandidates('es_ES'));
        $this->assertSame(['es_AR', 'es_ES', 'es'], ApplicationLocales::contentTranslationCandidates('es_AR'));
    }

    public function test_template_customizer_lang_maps_app_locales(): void
    {
        $this->assertSame('es', ApplicationLocales::templateCustomizerLang('es_ES'));
        $this->assertSame('es', ApplicationLocales::templateCustomizerLang('es_AR'));
        $this->assertSame('en', ApplicationLocales::templateCustomizerLang('en'));
        $this->assertSame('fr', ApplicationLocales::templateCustomizerLang('fr'));
        $this->assertSame('es', ApplicationLocales::javascriptLocale('es_ES'));
        $this->assertSame('es', ApplicationLocales::javascriptLocale('es_AR'));
        $this->assertSame('en', ApplicationLocales::javascriptLocale('en'));
    }
}
