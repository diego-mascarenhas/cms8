<?php

namespace Tests\Feature\Homes;

use App\Helpers\SupportWhatsAppHelper;
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
            ->assertSee('property="og:image" content="'.url('/images/system-onboarding/whatsapp-image.jpg').'"', false)
            ->assertSee('name="twitter:image" content="'.url('/images/system-onboarding/whatsapp-image.jpg').'"', false)
            ->assertSee('estándar superior', false)
            ->assertSee('Beneficios clave', false)
            ->assertSee('Seguro por diseño', false)
            ->assertSee(__('humano_pricing.hero_title'), false)
            ->assertSee(__('humano_pricing.billing_monthly'), false)
            ->assertSee(__('humano_pricing.billing_annual'), false)
            ->assertSee(__('humano_pricing.annual_discount_badge'), false)
            ->assertSee('slash-price-duration-toggler', false)
            ->assertSee('data-slash-checkout-monthly', false)
            ->assertSee('data-slash-checkout-yearly', false)
            ->assertSee('slash-price-yearly', false)
            ->assertSee('99€', false)
            ->assertSee(__('humano_pricing.billed_monthly'), false)
            ->assertSee(__('humano_pricing.subscribe'), false)
            ->assertSee('id="plan-assistant"', false)
            ->assertSee('slash-pricing-grid', false)
            ->assertSee(__('humano_pricing.landing_plans_title'), false)
            ->assertSee(__('humano_pricing.plans.assistant.name'), false)
            ->assertSee(__('humano_pricing.plans.hunter.name'), false)
            ->assertSee(__('humano_pricing.plans.business.name'), false)
            ->assertDontSee('id="plan-mentor"', false)
            ->assertDontSee('id="plan-innovation"', false)
            ->assertDontSee('id="historias-planes"', false)
            ->assertDontSee('data-slash-stories', false)
            ->assertDontSee(__('slash_landing.stories.title'), false)
            ->assertDontSee('slash-story-card', false)
            ->assertDontSee('id="producto"', false)
            ->assertDontSee(__('slash_landing.trust.title'), false)
            ->assertDontSee('María G.', false)
            ->assertSee(__('slash_landing.benefits.title'), false)
            ->assertSee(__('slash_landing.tools.title'), false)
            ->assertSee('id="planes"', false)
            ->assertSee('id="precios"', false)
            ->assertSee('id="guias"', false)
            ->assertSee('id="faq"', false)
            ->assertSee('id="contacto"', false)
            ->assertSee(__('Primeros pasos'), false)
            ->assertSee(\App\Support\GuidePresentation::url('primeros-pasos'), false)
            ->assertSee(\App\Support\GuidePresentation::url('calendario'), false)
            ->assertSee(__('Prospección'), false)
            ->assertSee(\App\Support\GuidePresentation::url('facturacion'), false)
            ->assertSee(__('Facturación'), false)
            ->assertSee('Ver presentación', false)
            ->assertSee(SlashHomeAsset::url('css/landing.css'), false)
            ->assertSee(asset('homes/shared/css/brand-footer.css'), false)
            ->assertSee(SlashHomeAsset::url('vendor/gsap/gsap.min.js'), false)
            ->assertSee(SlashHomeAsset::url('vendor/gsap/ScrollTrigger.min.js'), false)
            ->assertSee(SlashHomeAsset::url('vendor/lenis/lenis.min.js'), false)
            ->assertSee(SlashHomeAsset::url('js/landing.js'), false)
            ->assertSee('data-slash-counter="6"', false)
            ->assertSee('data-slash-counter="100"', false)
            ->assertSee(SlashHomeAsset::url('img/landing-page/hero-elements-dark.png'), false)
            ->assertSee(SlashHomeAsset::url('img/landing-page/hero-dashboard-dark.png'), false)
            ->assertSee(route('slash.lead.store'), false)
            ->assertSee('data-slash-lead-form', false)
            ->assertSee('data-slash-form-feedback', false)
            ->assertSee('novalidate', false)
            ->assertSee('slash-lead-config', false)
            ->assertSee(__('slash_landing.lead.validation_client_email_required'), false)
            ->assertSee('data-slash-lead-modal', false)
            ->assertSee('data-slash-lead-email', false)
            ->assertSee(__('slash_landing.lead.modal_submit_email_only'), false)
            ->assertSee(__('slash_landing.lead.modal_submit_with_details'), false)
            ->assertSee(__('humano_pricing.subscribe'), false)
            ->assertSee('https://web.whatsapp.com/send?phone='.SupportWhatsAppHelper::phoneDigits(), false)
            ->assertSee(SupportWhatsAppHelper::phoneDisplay(), false)
            ->assertSee(route('login'), false)
            ->assertSee('slash-nav-login-mobile', false)
            ->assertSee('hola@humano.app', false)
            ->assertSee('https://www.idoneo.dev', false)
            ->assertSee('https://revisionalpha.com', false)
            ->assertSee(__('slash_landing.footer.powered_by'), false)
            ->assertSee(asset('assets/logo-revision-alpha.svg'), false)
            ->assertSee('slash-footer-copy', false)
            ->assertSee(__('slash_landing.footer.brand_name'), false)
            ->assertSee('slash-footer-idoneo-word', false)
            ->assertSee('slash-footer-idoneo-letter', false)
            ->assertSee('--letter-index: 0', false)
            ->assertSee('slash-footer-idoneo-bolt', false)
            ->assertSee(asset('assets/logo-idoneo-iso.svg'), false)
            ->assertDontSee('Hecho con foco humano.', false)
            ->assertSee('¿Qué es Humano.app?', false)
            ->assertSee('¿Por qué usar Humano en lugar de Excel?', false);
    }

    public function test_slash_landing_footer_contact_links_to_whatsapp_web(): void
    {
        config([
            'app.whatsapp_support' => '',
            'app.wapify_whatsapp_phone' => '34613194131',
            'app.wapify_whatsapp_text' => '',
        ]);

        $this->get('/slash')
            ->assertOk()
            ->assertSee('https://web.whatsapp.com/send?phone=34613194131', false)
            ->assertSee('target="_blank"', false)
            ->assertSee(__('slash_landing.nav.contact'), false);
    }

    public function test_slash_landing_footer_contact_uses_whatsapp_support_number_when_set(): void
    {
        config([
            'app.whatsapp_support' => '+34 624 15 95 57',
            'app.wapify_whatsapp_phone' => '34613194131',
            'app.wapify_whatsapp_text' => '',
        ]);

        $this->get('/slash')
            ->assertOk()
            ->assertSee('https://web.whatsapp.com/send?phone=34624159557', false);
    }

    public function test_slash_landing_can_show_trust_and_plan_stories_sections_when_enabled(): void
    {
        config([
            'slash_landing.show_trust_section' => true,
            'slash_landing.show_plan_stories_section' => true,
        ]);

        $this->get('/slash')
            ->assertOk()
            ->assertSee('id="producto"', false)
            ->assertSee(__('slash_landing.trust.title'), false)
            ->assertSee('id="historias-planes"', false)
            ->assertSee(__('slash_landing.stories.title'), false)
            ->assertSee('data-slash-stories', false);
    }

    public function test_slash_landing_is_public_even_when_authenticated(): void
    {
        $this->get(route('slash'))
            ->assertOk()
            ->assertSee('slash-page', false);
    }
}
