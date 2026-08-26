<?php

namespace Tests\Unit;

use App\Services\TokenBillingRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenBillingRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_usd_sell_rate_is_ten_dollars_plus_markup(): void
    {
        config([
            'humano_pricing.token_billing.amount_per_million' => 10,
            'humano_pricing.token_billing.markup_percent' => 50,
            'humano_pricing.token_billing.currency' => 'USD',
        ]);

        $this->assertSame(15.0, TokenBillingRateService::usdSellRateFromConfig());
        $this->assertSame(15.0, TokenBillingRateService::displaySellRate());
        $this->assertSame(1500, TokenBillingRateService::cents(1_000_000));
        $this->assertSame('USD', TokenBillingRateService::baseCurrency());
    }
}
