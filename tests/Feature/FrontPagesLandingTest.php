<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontPagesLandingTest extends TestCase
{
    public function test_guest_can_view_humano_landing_page(): void
    {
        $this->get('/front-pages/landing')
            ->assertOk()
            ->assertSee('El asistente digital que trabaja por ti', false)
            ->assertSee('Beneficios clave', false)
            ->assertSee('#landingManuals', false)
            ->assertSee('#landingFAQ', false)
            ->assertSee(__('Primeros pasos'), false)
            ->assertSee(url('/humano-presentacion.html'), false)
            ->assertSee('Ver presentación', false)
            ->assertSee(route('pricing'), false)
            ->assertSee('humanoFrontNavCollapse', false)
            ->assertSee('Beneficios', false)
            ->assertSee('Guías', false)
            ->assertDontSee('landingPricing', false)
            ->assertDontSee(__('humano_pricing.hero_title'), false);
    }

    public function test_home_redirects_to_landing_when_public_home_path_is_set(): void
    {
        config([
            'app.public_home_route' => null,
            'app.public_home_path' => '/front-pages/landing',
        ]);

        $this->get('/')
            ->assertRedirect('/front-pages/landing');
    }
}
