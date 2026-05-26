<?php

namespace Tests\Feature\Homes;

use Tests\TestCase;

class HumanoLandingTest extends TestCase
{
    public function test_guest_can_view_humano_landing_at_root(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('El asistente digital que trabaja por ti', false)
            ->assertSee('Beneficios clave', false)
            ->assertSee('#landingManuals', false)
            ->assertSee('#landingFAQ', false)
            ->assertSee(__('Primeros pasos'), false)
            ->assertSee(url('/homes/humano/presentations/primeros-pasos.html'), false)
            ->assertSee('Ver presentación', false)
            ->assertSee(route('pricing'), false)
            ->assertSee('humanoFrontNavCollapse', false)
            ->assertSee('Beneficios', false)
            ->assertSee('Guías', false)
            ->assertDontSee('landingPricing', false)
            ->assertDontSee(__('humano_pricing.hero_title'), false);
    }

    public function test_legacy_front_pages_landing_redirects_to_root(): void
    {
        $this->get('/front-pages/landing')
            ->assertRedirect('/');
    }

    public function test_legacy_presentation_url_redirects_to_homes_path(): void
    {
        $this->get('/humano-presentacion.html')
            ->assertRedirect('/homes/humano/presentations/primeros-pasos.html');
    }
}
