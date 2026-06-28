<?php

namespace Tests\Unit\Models;

use App\Models\ExchangeRate;
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
