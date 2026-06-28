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
}
