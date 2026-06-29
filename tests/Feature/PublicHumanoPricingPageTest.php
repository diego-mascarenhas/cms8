<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicHumanoPricingPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.public_home_route' => 'humano',
            'app.public_home_path' => null,
        ]);

        if (empty(config('humano_pricing.plans')))
        {
            config(['humano_pricing' => require config_path('humano_pricing.php')]);
        }
    }

    public function test_pricing_page_renders_staging_stripe_links_without_prefilled_promo_in_url(): void
    {
        $response = $this->get('/pricing');

        $response->assertOk();
        $response->assertSee('humano-front-topnav', false);
        $response->assertSee('Beneficios', false);
        $response->assertSee(route('humano').'#landingFAQ', false);
        $response->assertSee(__('humano_pricing.plans.assistant.name'), false);
        $response->assertSee(__('humano_pricing.plans.hunter.name'), false);
        $response->assertSee(__('humano_pricing.plans.business.name'), false);
        $response->assertDontSee('id="plan-mentor"', false);
        $response->assertDontSee('id="plan-innovation"', false);
        $response->assertSee('5kQ4gzacZ3Nk9HM0Qd43S07', false);
        $response->assertSee('6oU9AT3OB5VsbPUeH343S06', false);
        $response->assertDontSee('4gM4gz3OB0B82fkcyV43S05', false);
        $response->assertDontSee(__('humano_pricing.coming_soon'), false);
        $response->assertSee(__('humano_pricing.most_popular'), false);
        $response->assertDontSee('prefilled_promo_code=', false);
        $this->assertDoesNotMatchRegularExpression(
            '/<a[^>]+href="https:\/\/buy\.stripe\.com[^"]*"[^>]*\btarget="_blank\b/',
            $response->getContent(),
        );
        $response->assertSee(__('humano_pricing.prices_plus_vat'), false);
        $response->assertSee('ti ti-point', false);
        $response->assertDontSee('Subscribe to newsletter', false);
        $response->assertDontSee('Most developer friendly', false);
        $response->assertDontSee('landing-footer', false);
        $response->assertDontSee(route('register'), false);
    }

    public function test_business_plan_shows_popular_badge_when_checkout_available(): void
    {
        $plans = collect(config('humano_pricing.plans', []))
            ->map(function (array $plan): array
            {
                if (($plan['id'] ?? '') === 'business')
                {
                    $plan['checkout_available'] = true;
                }

                return $plan;
            })
            ->all();

        config(['humano_pricing.plans' => $plans]);

        $response = $this->get('/pricing');

        $response->assertOk();
        $response->assertSee(__('humano_pricing.most_popular'), false);
        $response->assertSee('6oU9AT3OB5VsbPUeH343S06', false);
    }

    public function test_front_pages_pricing_path_is_registered(): void
    {
        $this->get('/front-pages/pricing')->assertOk();
    }

    public function test_pricing_redirects_to_slash_landing_when_slash_is_public_home(): void
    {
        config([
            'app.public_home_route' => 'slash',
            'app.public_home_path' => null,
        ]);

        $this->get('/pricing')
            ->assertRedirect(route('slash').'#precios');
    }

    public function test_pricing_is_accessible_when_humano_is_public_home(): void
    {
        config([
            'app.public_home_route' => 'humano',
            'app.public_home_path' => null,
        ]);

        $this->get('/pricing')->assertOk();
    }
}
