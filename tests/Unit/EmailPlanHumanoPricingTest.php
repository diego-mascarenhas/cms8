<?php

namespace Tests\Unit;

use App\Enums\EmailPlan;
use Tests\TestCase;

class EmailPlanHumanoPricingTest extends TestCase
{
    public function test_mailer_ids_resolve_from_humano_pricing(): void
    {
        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'mailer_basic',
                    'catalog' => 'mailer',
                    'stripe_product_id' => 'prod_from_pricing',
                    'stripe_price_monthly_id' => 'price_from_pricing',
                ],
            ],
        ]);

        $this->assertSame('prod_from_pricing', EmailPlan::BASIC->getStripeProductId());
        $this->assertSame('price_from_pricing', EmailPlan::BASIC->getStripePriceId());
        $this->assertSame(EmailPlan::BASIC, EmailPlan::tryFromStripeProductId('prod_from_pricing'));
        $this->assertSame(EmailPlan::BASIC, EmailPlan::fromStripePriceId('price_from_pricing'));
    }
}
