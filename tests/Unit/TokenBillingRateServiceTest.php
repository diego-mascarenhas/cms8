<?php

namespace Tests\Unit;

use App\Models\ExchangeRate;
use App\Services\TokenBillingRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenBillingRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_multiplier_and_display_currency(): void
    {
        config([
            'humano_pricing.token_billing.currency' => 'USD',
            'humano_pricing.token_billing.client_token_multiplier' => 2,
        ]);

        $this->assertSame(2.0, TokenBillingRateService::clientTokenMultiplier());
        $this->assertSame(1.0, TokenBillingRateService::usdToDisplay());
        $this->assertSame('USD', TokenBillingRateService::baseCurrency());
        $this->assertSame('USD', TokenBillingRateService::displayCurrency());
    }

    public function test_default_client_multiplier_is_ten(): void
    {
        $this->assertSame(10.0, TokenBillingRateService::clientTokenMultiplier());
    }

    public function test_client_multiplier_uses_the_team_config_override(): void
    {
        config(['humano_pricing.token_billing.client_token_multiplier_by_team' => [99 => 4]]);

        $this->assertSame(4.0, TokenBillingRateService::clientTokenMultiplier(99));
        $this->assertSame(10.0, TokenBillingRateService::clientTokenMultiplier(100));
    }

    public function test_usd_to_display_uses_exchange_rate_history(): void
    {
        config([
            'humano_pricing.token_billing.base_currency' => 'USD',
            'humano_pricing.token_billing.currency' => 'EUR',
        ]);

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => now()->toDateString(),
            'fetched_at' => now(),
        ]);

        $this->assertEqualsWithDelta(0.90, TokenBillingRateService::usdToDisplay(), 0.0001);
    }
}
