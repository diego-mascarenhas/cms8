<?php

namespace Tests\Feature\Homes;

use Tests\TestCase;

class PerformanceInsightLandingTest extends TestCase
{
    public function test_insight_landing_uses_spain_friendly_terms_by_default(): void
    {
        $this->get('/insight-diario')
            ->assertOk()
            ->assertSee('informe del día', false)
            ->assertSee('Informe listo para actuar', false)
            ->assertDontSee('briefing del día', false)
            ->assertDontSee('Briefing listo para actuar', false);
    }

    public function test_insight_landing_keeps_argentina_terms_when_session_locale_is_es_ar(): void
    {
        $this->withSession(['locale' => 'es_AR'])
            ->get('/insight-diario')
            ->assertOk()
            ->assertSee('briefing del día', false)
            ->assertSee('Briefing listo para actuar', false);
    }
}
