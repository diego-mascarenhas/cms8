<?php

namespace Tests\Unit;

use App\Services\HumanoPricingPlanResolver;
use Tests\TestCase;

class HumanoPricingPlanResolverTest extends TestCase
{
    public function test_plans_for_display_expose_monthly_and_yearly_checkout_hrefs(): void
    {
        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'checkout_url' => 'https://buy.stripe.com/monthly-assistant',
                    'checkout_url_yearly' => 'https://buy.stripe.com/yearly-assistant',
                    'checkout_available' => true,
                ],
            ],
        ]);

        $plans = app(HumanoPricingPlanResolver::class)->plansForDisplay();

        $this->assertSame('https://buy.stripe.com/monthly-assistant', $plans[0]['checkout_href_monthly']);
        $this->assertSame('https://buy.stripe.com/yearly-assistant', $plans[0]['checkout_href_yearly']);
        $this->assertSame('https://buy.stripe.com/monthly-assistant', $plans[0]['checkout_href']);
    }

    public function test_yearly_checkout_href_falls_back_to_monthly_when_not_configured(): void
    {
        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'checkout_url' => 'https://buy.stripe.com/monthly-assistant',
                    'checkout_available' => true,
                ],
            ],
        ]);

        $plans = app(HumanoPricingPlanResolver::class)->plansForDisplay();

        $this->assertSame('https://buy.stripe.com/monthly-assistant', $plans[0]['checkout_href_yearly']);
    }

    public function test_plans_with_checkout_available_only_includes_checkout_enabled_plans(): void
    {
        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'checkout_url' => 'https://buy.stripe.com/monthly-assistant',
                    'checkout_available' => true,
                ],
                [
                    'id' => 'mentor',
                    'checkout_url' => '',
                    'checkout_available' => false,
                ],
            ],
        ]);

        $plans = app(HumanoPricingPlanResolver::class)->plansWithCheckoutAvailable();

        $this->assertCount(1, $plans);
        $this->assertSame('assistant', $plans[0]['id']);
    }

    public function test_public_display_excludes_spa_only_mailer_plans(): void
    {
        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'catalog' => 'assistant',
                    'public' => true,
                    'checkout_available' => true,
                ],
                [
                    'id' => 'mailer_basic',
                    'catalog' => 'mailer',
                    'public' => false,
                    'checkout_available' => true,
                ],
            ],
        ]);

        $resolver = app(HumanoPricingPlanResolver::class);

        $this->assertSame(['assistant'], collect($resolver->plansForPublicDisplay())->pluck('id')->all());
        $this->assertSame(['mailer_basic'], collect($resolver->plansForCatalog('mailer'))->pluck('id')->all());
        $this->assertSame(['assistant'], collect($resolver->plansWithCheckoutAvailable())->pluck('id')->all());
    }

    public function test_plan_by_stripe_product_id_returns_mailer_config(): void
    {
        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'mailer_basic',
                    'catalog' => 'mailer',
                    'public' => false,
                    'subscription_type' => 'mailer',
                    'stripe_product_id' => 'prod_mailer_basic',
                    'checkout_available' => true,
                ],
            ],
        ]);

        $plan = app(HumanoPricingPlanResolver::class)->planByStripeProductId('prod_mailer_basic');

        $this->assertSame('mailer_basic', $plan['id'] ?? null);
        $this->assertSame('mailer', $plan['subscription_type'] ?? null);
        $this->assertSame('mailer_basic', app(HumanoPricingPlanResolver::class)->resolvePlanSlugFromStripeProductId('prod_mailer_basic'));
    }
}
