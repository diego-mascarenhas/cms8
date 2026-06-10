<?php

namespace Tests\Feature\Homes;

use App\Support\SlashHomeAsset;
use Tests\TestCase;

class SlashLandingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.public_home_route' => 'slash',
            'app.public_home_path' => null,
        ]);
    }

    public function test_slash_landing_returns_404_when_not_configured_as_public_home(): void
    {
        config([
            'app.public_home_route' => null,
            'app.public_home_path' => null,
        ]);

        $this->get('/slash')
            ->assertNotFound();
    }

    public function test_slash_landing_returns_404_when_another_public_home_route_is_set(): void
    {
        config([
            'app.public_home_route' => 'humano',
            'app.public_home_path' => null,
        ]);

        $this->get('/slash')
            ->assertNotFound();
    }

    public function test_slash_landing_is_available_when_public_home_path_is_slash(): void
    {
        config([
            'app.public_home_route' => null,
            'app.public_home_path' => '/slash',
        ]);

        $this->get('/slash')
            ->assertOk()
            ->assertSee('slash-page', false);
    }

    public function test_guest_can_view_slash_landing(): void
    {
        $this->get('/slash')
            ->assertOk()
            ->assertSee('slash-page', false)
            ->assertSee('slash-hero-glows', false)
            ->assertSee('slash-glow-frame', false)
            ->assertSee('data-slash-spotlight', false)
            ->assertSee('color-scheme" content="dark"', false)
            ->assertSee('estándar superior', false)
            ->assertSee('Beneficios clave', false)
            ->assertSee('Seguro por diseño', false)
            ->assertSee('Precios transparentes', false)
            ->assertSee(__('humano_pricing.landing_plans_title'), false)
            ->assertSee(__('humano_pricing.plans.assistant.name'), false)
            ->assertSee(__('humano_pricing.plans.hunter.name'), false)
            ->assertSee(__('humano_pricing.plans.business.name'), false)
            ->assertSee(__('humano_pricing.plans.mentor.name'), false)
            ->assertSee('#plan-assistant', false)
            ->assertSee('id="historias-planes"', false)
            ->assertSee('data-slash-stories', false)
            ->assertSee('slash-stories-row', false)
            ->assertSee('Conocé cada plan en acción', false)
            ->assertSee('slash-story-card', false)
            ->assertSee('id="producto"', false)
            ->assertSee('id="planes"', false)
            ->assertSee('id="precios"', false)
            ->assertSee('id="guias"', false)
            ->assertSee('id="faq"', false)
            ->assertSee('id="contacto"', false)
            ->assertSee(__('Primeros pasos'), false)
            ->assertSee(\App\Support\HumanoHomeAsset::url('presentations/primeros-pasos.html'), false)
            ->assertSee(\App\Support\HumanoHomeAsset::url('presentations/calendario.html'), false)
            ->assertSee(__('Prospección'), false)
            ->assertSee('Ver presentación', false)
            ->assertSee(SlashHomeAsset::url('css/landing.css'), false)
            ->assertSee(SlashHomeAsset::url('vendor/gsap/gsap.min.js'), false)
            ->assertSee(SlashHomeAsset::url('vendor/gsap/ScrollTrigger.min.js'), false)
            ->assertSee(SlashHomeAsset::url('vendor/lenis/lenis.min.js'), false)
            ->assertSee(SlashHomeAsset::url('js/landing.js'), false)
            ->assertSee('data-slash-counter="6"', false)
            ->assertSee('data-slash-counter="100"', false)
            ->assertSee(SlashHomeAsset::url('img/landing-page/hero-elements-dark.png'), false)
            ->assertSee(SlashHomeAsset::url('img/landing-page/hero-dashboard-dark.png'), false)
            ->assertSee(route('pricing'), false)
            ->assertSee(route('login'), false)
            ->assertSee('hola@humano.app', false)
            ->assertSee('¿Qué es Humano.app?', false)
            ->assertSee('¿Por qué usar Humano en lugar de Excel?', false);
    }

    public function test_slash_landing_is_public_even_when_authenticated(): void
    {
        $this->get(route('slash'))
            ->assertOk()
            ->assertSee('slash-page', false);
    }
}
