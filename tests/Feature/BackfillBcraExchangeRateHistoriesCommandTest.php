<?php

namespace Tests\Feature;

use App\Models\ExchangeRateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackfillBcraExchangeRateHistoriesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_bcra_stores_monthly_usd_ars_rate(): void
    {
        Http::fake([
            'api.bcra.gob.ar/*' => Http::response([
                'status' => 200,
                'metadata' => [
                    'resultset' => [
                        'count' => 1,
                        'offset' => 0,
                        'limit' => 1000,
                    ],
                ],
                'results' => [
                    [
                        'fecha' => '2011-01-31',
                        'detalle' => [
                            ['codigoMoneda' => 'USD', 'tipoCotizacion' => 4.008],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('exchange-rates:backfill-bcra', [
            '--from' => '2011-01',
            '--to' => '2011-01',
            '--sleep' => 0,
        ])->assertSuccessful();

        $history = ExchangeRateHistory::query()
            ->where('base_currency', 'USD')
            ->where('target_currency', 'ARS')
            ->whereDate('rate_month', '2011-01-01')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('4.00800000', $history->rate);
        $this->assertSame('bcra', $history->provider);
    }

    public function test_backfill_bcra_skips_existing_month_when_requested(): void
    {
        ExchangeRateHistory::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'ARS',
            'rate_month' => '2011-01-01',
            'rate' => 4.10,
            'fetched_at' => now(),
            'provider' => 'legacy',
        ]);

        Http::fake();

        $this->artisan('exchange-rates:backfill-bcra', [
            '--from' => '2011-01',
            '--to' => '2011-01',
            '--skip-existing' => true,
            '--sleep' => 0,
        ])->assertSuccessful();

        Http::assertNothingSent();

        $this->assertSame(
            '4.10000000',
            ExchangeRateHistory::query()->whereDate('rate_month', '2011-01-01')->value('rate'),
        );
    }
}
