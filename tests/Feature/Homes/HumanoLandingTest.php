<?php

namespace Tests\Feature\Homes;

use App\Helpers\SupportWhatsAppHelper;
use App\Support\GuidePresentation;
use App\Support\HumanoHomeAsset;
use Tests\TestCase;

class HumanoLandingTest extends TestCase
{
    public function test_guest_can_view_humano_landing_at_inicio(): void
    {
        $this->get('/inicio')
            ->assertOk()
            ->assertSee('property="og:description" content="La libertad de trabajar donde quieras, cuando quieras. Eso es HumanoApp."', false)
            ->assertSee('property="og:title" content="HumanoApp"', false)
            ->assertSee('La nueva forma de gestionar tu negocio.', false)
            ->assertSee('highlight-whatsapp', false)
            ->assertSee('highlight-brand', false)
            ->assertSee('ligero, rápido e intuitivo', false)
            ->assertSee('Beneficios clave', false)
            ->assertSee(__('humano_pricing.landing_plans_title'), false)
            ->assertSee(__('humano_pricing.plans.hunter.name'), false)
            ->assertSee('landingPlans', false)
            ->assertDontSee('landingFunFacts', false)
            ->assertSee('#landingManuals', false)
            ->assertSee('#landingPlans', false)
            ->assertSee('#landingFAQ', false)
            ->assertSee(__('Primeros pasos'), false)
            ->assertSee(GuidePresentation::url('primeros-pasos'), false)
            ->assertSee(GuidePresentation::url('chat-contactos-modulos'), false)
            ->assertSee('Chat, contactos y módulos', false)
            ->assertSee(GuidePresentation::url('calendario'), false)
            ->assertSee(__('Calendario'), false)
            ->assertSee(GuidePresentation::url('tareas'), false)
            ->assertSee(__('Tareas'), false)
            ->assertSee(GuidePresentation::url('prospeccion'), false)
            ->assertSee(__('Prospección'), false)
            ->assertSee(GuidePresentation::url('lista-de-60'), false)
            ->assertSee(__('list60'), false)
            ->assertSee(GuidePresentation::url('facturacion'), false)
            ->assertSee(__('Facturación'), false)
            ->assertSee(GuidePresentation::url('afiliados'), false)
            ->assertSee(__('Afiliados'), false)
            ->assertSee(GuidePresentation::url('cms-wordpress'), false)
            ->assertSee(__('CMS y WordPress'), false)
            ->assertSee(\App\Support\HumanoHomeAsset::url('css/landing.css'), false)
            ->assertSee('Ver presentación', false)
            ->assertSee('https://www.youtube.com/playlist?list=PLebHHjcT7KEc', false)
            ->assertSee(__('slash_landing.nav.youtube_tutorials'), false)
            ->assertSee(__('slash_landing.guides.youtube_card.title'), false)
            ->assertSee(route('pricing'), false)
            ->assertSee('humanoFrontNavCollapse', false)
            ->assertDontSee('landingPricing', false)
            ->assertDontSee(__('humano_pricing.hero_title'), false);
    }

    public function test_landing_plans_without_checkout_show_consult_cta(): void
    {
        $this->get('/inicio')
            ->assertOk()
            ->assertSee(__('humano_pricing.consult_cta'), false)
            ->assertSee('https://web.whatsapp.com/send?phone='.SupportWhatsAppHelper::phoneDigits(), false)
            ->assertDontSee('#plan-mentor', false)
            ->assertDontSee('https://fanyion.com', false)
            ->assertSee(__('humano_pricing.landing_plans_cta'), false);
    }

    public function test_landing_includes_brand_footer_with_partner_effects(): void
    {
        $this->get('/inicio')
            ->assertOk()
            ->assertSee('humano-brand-footer', false)
            ->assertSee('slash-footer-idoneo', false)
            ->assertSee('slash-footer-powered', false)
            ->assertSee(__('slash_landing.footer.brand_name'), false)
            ->assertSee(__('slash_landing.footer.copyright'), false)
            ->assertSee(__('slash_landing.footer.powered_by'), false)
            ->assertSee(asset('assets/logo-idoneo-iso.svg'), false)
            ->assertSee(asset('assets/logo-revision-alpha.svg'), false)
            ->assertSee(asset('homes/shared/css/brand-footer.css'), false)
            ->assertSee(HumanoHomeAsset::url('vendor/lenis/lenis.min.js'), false)
            ->assertSee(HumanoHomeAsset::url('vendor/gsap/gsap.min.js'), false);
    }

    public function test_public_index_html_must_not_exist_to_avoid_root_reload_loop(): void
    {
        $this->assertFileDoesNotExist(public_path('index.html'));
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

    public function test_guest_root_redirects_to_slash_when_public_home_route_is_set(): void
    {
        config([
            'app.public_home_route' => 'slash',
            'app.public_home_path' => null,
        ]);

        $this->get('/')
            ->assertRedirect(route('slash'));
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

    public function test_legacy_presentation_url_redirects_to_presentacion_route(): void
    {
        $this->get('/humano-presentacion.html')
            ->assertRedirect('/presentacion/primeros-pasos');
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
