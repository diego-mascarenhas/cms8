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

    public function test_payg_plan_has_no_stripe_ids_and_uses_credits(): void
    {
        $this->assertTrue(EmailPlan::PAYG->usesPrepaidCredits());
        $this->assertSame(0, EmailPlan::PAYG->getMonthlyLimit());
        $this->assertNull(EmailPlan::PAYG->getDailyLimit());
        $this->assertNull(EmailPlan::PAYG->getStripeProductId());
        $this->assertNull(EmailPlan::PAYG->getStripePriceId());
        $this->assertSame('Pay as you go', EmailPlan::PAYG->getDisplayName());
    }
}
