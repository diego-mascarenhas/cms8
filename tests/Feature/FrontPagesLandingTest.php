<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontPagesLandingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('humano_pricing.plans')))
        {
            config(['humano_pricing' => require config_path('humano_pricing.php')]);
        }
    }

    public function test_guest_can_view_humano_landing_page(): void
    {
        $this->get('/front-pages/landing')
            ->assertOk()
            ->assertSee('El asistente digital que trabaja por ti', false)
            ->assertSee('Beneficios clave', false)
            ->assertSee(__('humano_pricing.hero_title'), false)
            ->assertSee(__('humano_pricing.subscribe'), false)
            ->assertSee('price-duration-toggler', false);
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
