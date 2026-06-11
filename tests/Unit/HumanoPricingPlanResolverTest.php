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
}
