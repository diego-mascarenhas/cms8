<?php

namespace Tests\Feature;

use App\Models\ExchangeRateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackfillFrankfurterExchangeRateHistoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_frankfurter_stores_monthly_usd_eur_rate(): void
    {
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0,
                'base' => 'USD',
                'start_date' => '2011-01-31',
                'end_date' => '2011-01-31',
                'rates' => [
                    '2011-01-31' => ['EUR' => 0.73035],
                ],
            ], 200),
        ]);

        $this->artisan('exchange-rates:backfill-frankfurter', [
            '--from' => '2011-01',
            '--to' => '2011-01',
            '--sleep' => 0,
        ])->assertSuccessful();

        $history = ExchangeRateHistory::query()
            ->where('base_currency', 'USD')
            ->where('target_currency', 'EUR')
            ->whereDate('rate_month', '2011-01-01')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('0.73035000', $history->rate);
        $this->assertSame('frankfurter', $history->provider);
    }
}
