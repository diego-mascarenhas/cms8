<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\TokenBillingHistory;
use App\Services\TokenBillingRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenBillingHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_stores_usd_cost_markup_and_sell_rate_for_the_month(): void
    {
        config([
            'humano_pricing.token_billing.amount_per_million' => 10,
            'humano_pricing.token_billing.markup_percent' => 50,
        ]);

        $this->assertSame('created', TokenBillingRateService::syncCurrentMonth());
        $this->assertSame('skipped', TokenBillingRateService::syncCurrentMonth());

        $row = TokenBillingHistory::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('USD', $row->base_currency);
        $this->assertSame('10.0000', $row->amount_per_million);
        $this->assertSame('50.00', $row->markup_percent);
        $this->assertSame('15.0000', $row->sell_rate);
        $this->assertSame(now()->startOfMonth()->toDateString(), $row->rate_month->toDateString());
    }

    public function test_sync_updates_the_current_month_when_markup_changes(): void
    {
        TokenBillingRateService::syncCurrentMonth();

        config(['humano_pricing.token_billing.markup_percent' => 80]);

        $this->assertSame('updated', TokenBillingRateService::syncCurrentMonth());
        $this->assertSame(1, TokenBillingHistory::query()->count());
        $this->assertSame('18.0000', TokenBillingHistory::query()->value('sell_rate'));
    }

    public function test_display_rate_uses_the_ledger_month_on_or_before_the_date(): void
    {
        config([
            'humano_pricing.token_billing.amount_per_million' => 10,
            'humano_pricing.token_billing.markup_percent' => 80,
            'humano_pricing.token_billing.currency' => 'USD',
        ]);

        TokenBillingHistory::factory()->create([
            'rate_month' => now()->subMonth()->startOfMonth()->toDateString(),
            'amount_per_million' => 10,
            'markup_percent' => 50,
            'sell_rate' => 15,
        ]);

        $this->assertSame(15.0, TokenBillingRateService::displaySellRate(now()->subMonth()->startOfMonth()->addDays(10)));
        $this->assertSame(18.0, TokenBillingRateService::displaySellRate(now()));
    }

    public function test_display_rate_converts_usd_with_exchange_rate_history(): void
    {
        config([
            'humano_pricing.token_billing.amount_per_million' => 10,
            'humano_pricing.token_billing.markup_percent' => 50,
            'humano_pricing.token_billing.currency' => 'EUR',
        ]);

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => now()->toDateString(),
            'fetched_at' => now(),
        ]);

        $this->assertEqualsWithDelta(13.5, TokenBillingRateService::displaySellRate(), 0.0001);
        $this->assertSame(1350, TokenBillingRateService::cents(1_000_000));
    }
}
