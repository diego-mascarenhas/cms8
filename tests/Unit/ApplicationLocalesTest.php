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

    /**
     * Asserts the wiring, not the wording: the keys resolve and the default locale is served from
     * the es_ES dictionary. Pinning the marketing copy itself only breaks on every rewrite.
     */
    public function test_slash_landing_copy_defaults_to_spain_spanish(): void
    {
        app()->setLocale(ApplicationLocales::DEFAULT);

        foreach (['slash_landing.stories.title', 'slash_landing.benefits.title', 'slash_landing.tools.title'] as $key)
        {
            $this->assertNotSame($key, __($key), "Missing es_ES copy for {$key}.");
            $this->assertSame(__($key, [], 'es_ES'), __($key));
        }
    }

    public function test_spain_and_argentina_dictionaries_differ(): void
    {
        $key = 'humano_pricing.landing_plans_subtitle';
        $spain = __($key, [], 'es_ES');
        $argentina = __($key, [], 'es_AR');

        $this->assertNotSame($key, $spain);
        $this->assertNotSame($key, $argentina);
        $this->assertNotSame($spain, $argentina, 'es_AR must keep its own voseo copy instead of falling back to es_ES.');
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

    public function test_html_lang_uses_bcp47_hyphen_format(): void
    {
        $this->assertSame('es-ES', ApplicationLocales::htmlLang('es_ES'));
        $this->assertSame('es-AR', ApplicationLocales::htmlLang('es_AR'));
        $this->assertSame('en', ApplicationLocales::htmlLang('en'));
        $this->assertSame('es-ES', ApplicationLocales::htmlLang('es'));
    }

    public function test_navbar_selector_only_offers_english_and_spanish(): void
    {
        $options = ApplicationLocales::navbarSelectorOptions();

        $this->assertCount(2, $options);
        $this->assertSame('en', $options[0]['route']);
        $this->assertSame('es', $options[1]['route']);

        app()->setLocale('es_ES');
        $this->assertTrue(ApplicationLocales::isNavbarSelectorActive('es_ES'));
        $this->assertFalse(ApplicationLocales::isNavbarSelectorActive('en'));

        app()->setLocale('en');
        $this->assertTrue(ApplicationLocales::isNavbarSelectorActive('en'));
        $this->assertFalse(ApplicationLocales::isNavbarSelectorActive('es_ES'));
    }
}
