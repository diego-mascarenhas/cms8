<?php

namespace Tests\Feature\Homes;

use Tests\TestCase;

class HumanoLandingTest extends TestCase
{
    public function test_guest_can_view_humano_landing_at_inicio(): void
    {
        $this->get('/inicio')
            ->assertOk()
            ->assertSee('El asistente digital que trabaja por ti', false)
            ->assertSee('Beneficios clave', false)
            ->assertSee(__('humano_pricing.landing_plans_title'), false)
            ->assertSee(__('humano_pricing.plans.hunter.name'), false)
            ->assertSee('landingPlans', false)
            ->assertDontSee('landingFunFacts', false)
            ->assertSee('#landingManuals', false)
            ->assertSee('#landingPlans', false)
            ->assertSee('#landingFAQ', false)
            ->assertSee(__('Primeros pasos'), false)
            ->assertSee(\App\Support\HumanoHomeAsset::url('presentations/primeros-pasos.html'), false)
            ->assertSee(\App\Support\HumanoHomeAsset::url('presentations/chat-contactos-modulos.html'), false)
            ->assertSee('Chat, contactos y módulos', false)
            ->assertSee(\App\Support\HumanoHomeAsset::url('css/landing.css'), false)
            ->assertSee('Ver presentación', false)
            ->assertSee(route('pricing'), false)
            ->assertSee('humanoFrontNavCollapse', false)
            ->assertDontSee('landingPricing', false)
            ->assertDontSee(__('humano_pricing.hero_title'), false);
    }

    public function test_guest_root_redirects_to_login_by_default(): void
    {
        config([
            'app.public_home_route' => null,
            'app.public_home_path' => null,
        ]);

        $this->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_guest_root_redirects_to_humano_when_public_home_route_is_set(): void
    {
        config([
            'app.public_home_route' => 'humano',
            'app.public_home_path' => null,
        ]);

        $this->get('/')
            ->assertRedirect(route('humano'));
    }

    public function test_guest_root_redirects_to_wapify_when_public_home_route_is_set(): void
    {
        config([
            'app.public_home_route' => 'wapify',
            'app.public_home_path' => null,
        ]);

        $this->get('/')
            ->assertRedirect(route('wapify'));
    }

    public function test_guest_root_redirects_to_public_home_path_when_set(): void
    {
        config([
            'app.public_home_route' => null,
            'app.public_home_path' => '/inicio',
        ]);

        $this->get('/')
            ->assertRedirect('/inicio');
    }

    public function test_public_home_route_takes_precedence_over_path_when_both_set(): void
    {
        config([
            'app.public_home_route' => 'wapify',
            'app.public_home_path' => '/inicio',
        ]);

        $this->get('/')
            ->assertRedirect(route('wapify'));
    }

    public function test_legacy_front_pages_landing_redirects_to_inicio(): void
    {
        $this->get('/front-pages/landing')
            ->assertRedirect('/inicio');
    }

    public function test_legacy_presentation_url_redirects_to_homes_path(): void
    {
        $this->get('/humano-presentacion.html')
            ->assertRedirect('/homes/humano/presentations/primeros-pasos.html');
    }

    public function test_chat_whatsapp_presentation_embed_is_public(): void
    {
        $this->get(route('humano.presentation.chat-whatsapp-embed'))
            ->assertOk()
            ->assertSee('+34 999 000 999', false)
            ->assertSee(__('Scan QR'), false)
            ->assertSee(__('WhatsApp connection'), false)
            ->assertSee('homes/humano/img/presentations/whatsapp-qr.png', false);
    }
}
