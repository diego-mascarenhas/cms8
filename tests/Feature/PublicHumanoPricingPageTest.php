<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicHumanoPricingPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('humano_pricing.plans')))
        {
            config(['humano_pricing' => require config_path('humano_pricing.php')]);
        }
    }

    public function test_pricing_page_renders_staging_stripe_links_and_prefilled_coupon(): void
    {
        $response = $this->get('/pricing');

        $response->assertOk();
        $response->assertSee('humano-front-topnav', false);
        $response->assertSee('Humano.app Assistant', false);
        $response->assertSee('Humano.app Business', false);
        $response->assertSee('Humano.app Foundation', false);
        $response->assertSee('3cIeVd98VabI07cgPb43S03', false);
        $response->assertSee('prefilled_promo_code=SOYAMIGO', false);
        $response->assertSee(__('humano_pricing.prices_plus_vat'), false);
        $response->assertSee('ti ti-point', false);
        $response->assertDontSee('Subscribe to newsletter', false);
        $response->assertDontSee('Most developer friendly', false);
        $response->assertDontSee('landing-footer', false);
        $response->assertDontSee(route('register'), false);
    }

    public function test_front_pages_pricing_path_is_registered(): void
    {
        $this->get('/front-pages/pricing')->assertOk();
    }
}
