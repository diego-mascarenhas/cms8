<?php

namespace Tests\Unit\Models;

use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_latest_rate_uses_inverse_pair_when_direct_rate_missing(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => now()->toDateString(),
            'fetched_at' => now(),
        ]);

        $this->assertEqualsWithDelta(1 / 0.90, ExchangeRate::getLatestRate('EUR', 'USD'), 0.0001);
    }

    public function test_rate_on_or_before_date_keeps_full_precision_for_weak_currencies(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'rate' => 1000,
            'date' => '2024-05-01',
            'fetched_at' => now(),
        ]);
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => '2024-05-01',
            'fetched_at' => now(),
        ]);

        // 1 ARS = (1/1000)*0.90 EUR = 0.0009
        $rate = ExchangeRate::rateOnOrBeforeDate('ARS', 'EUR', '2024-05-10');

        $this->assertNotNull($rate);
        $this->assertEqualsWithDelta(0.0009, $rate, 0.0000001);
        $this->assertEqualsWithDelta(0.90, ExchangeRate::convertOnOrBeforeDate(1000, 'ARS', 'EUR', '2024-05-10'), 0.01);
    }

    public function test_invoice_date_uses_that_days_quote_instead_of_the_monthly_history(): void
    {
        ExchangeRateHistory::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'rate_month' => '2026-05-01',
            'rate' => 1408,
            'fetched_at' => now(),
            'provider' => 'bcra',
        ]);
        ExchangeRateHistory::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate_month' => '2026-06-01',
            'rate' => 0.87712,
            'fetched_at' => now(),
            'provider' => 'frankfurter',
        ]);

        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'rate' => 1500,
            'date' => '2026-07-24',
            'fetched_at' => now(),
        ]);
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.86,
            'date' => '2026-07-24',
            'fetched_at' => now(),
        ]);
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'rate' => 1600,
            'date' => '2026-09-25',
            'fetched_at' => now(),
        ]);
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.88,
            'date' => '2026-09-25',
            'fetched_at' => now(),
        ]);

        $this->assertEqualsWithDelta(0.86 / 1500, ExchangeRate::rateOnOrBeforeDate('ARS', 'EUR', '2026-07-24'), 0.0000001);
        $this->assertEqualsWithDelta(0.88 / 1600, ExchangeRate::rateOnOrBeforeDate('ARS', 'EUR', '2026-09-25'), 0.0000001);
        $this->assertEqualsWithDelta(0.86 / 1500, ExchangeRate::rateOnOrBeforeDate('ARS', 'EUR', '2026-07-26'), 0.0000001);
    }

    public function test_store_daily_if_changed_creates_new_rate(): void
    {
        $action = ExchangeRate::storeDailyIfChanged('USD', 'ARS', '2011-01-31', 4.008);

        $this->assertSame('created', $action);
        $this->assertSame('4.00800000', ExchangeRate::query()->value('rate'));
    }

    public function test_store_daily_if_changed_skips_unchanged_rate(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'rate' => 4.008,
            'date' => '2011-01-31',
            'fetched_at' => now()->subHour(),
        ]);

        $action = ExchangeRate::storeDailyIfChanged('USD', 'ARS', '2011-01-31', 4.008);

        $this->assertSame('skipped', $action);
        $this->assertSame(1, ExchangeRate::query()->count());
    }

    public function test_store_daily_if_changed_updates_when_rate_changes(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => '2011-01-31',
            'fetched_at' => now()->subHour(),
        ]);

        $action = ExchangeRate::storeDailyIfChanged('USD', 'EUR', '2011-01-31', 0.91);

        $this->assertSame('updated', $action);
        $this->assertSame('0.91000000', ExchangeRate::query()->value('rate'));
    }
}
