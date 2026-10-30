<?php

namespace Tests\Unit;

use App\Support\MailerPaygPricing;
use Tests\TestCase;

class MailerPaygPricingTest extends TestCase
{
    public function test_overage_rate_comes_from_emailer_config(): void
    {
        $this->assertSame('0.003', MailerPaygPricing::pricePerEmail());
        $this->assertSame('USD', MailerPaygPricing::currency());
        $this->assertSame(1, MailerPaygPricing::amountToCents('0.01'));
        $this->assertSame(0, MailerPaygPricing::overageDueCents(0));
        $this->assertSame(0, MailerPaygPricing::overageDueCents(1));
        $this->assertSame(60, MailerPaygPricing::overageDueCents(200));
    }
}
