<?php

namespace Tests\Feature;

use Tests\TestCase;

class AffiliatePlanCheckoutTest extends TestCase
{
    public function test_unknown_plan_redirects_to_pricing(): void
    {
        $this->get('/affiliate/checkout/does-not-exist?ref=cus_referrer')
            ->assertRedirect(route('pricing'));
    }

    public function test_plan_without_stripe_price_keeps_the_referrer_on_pricing(): void
    {
        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'estimator',
                    'checkout_url' => '',
                    'stripe_price_monthly_id' => '',
                ],
            ],
        ]);

        $this->get('/affiliate/checkout/estimator?ref=cus_referrer')
            ->assertRedirect(route('pricing', ['plan' => 'estimator', 'ref' => 'cus_referrer']));
    }
}
