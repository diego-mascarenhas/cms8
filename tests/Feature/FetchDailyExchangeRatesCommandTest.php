<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchDailyExchangeRatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_daily_stores_bcra_and_frankfurter_rates(): void
    {
        $today = now()->toDateString();

        Http::fake([
            'api.bcra.gob.ar/*' => Http::response([
                'status' => 200,
                'metadata' => ['resultset' => ['count' => 1, 'offset' => 0, 'limit' => 1000]],
                'results' => [
                    [
                        'fecha' => $today,
                        'detalle' => [
                            ['codigoMoneda' => 'USD', 'tipoCotizacion' => 4.008],
                        ],
                    ],
                ],
            ], 200),
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0,
                'base' => 'USD',
                'date' => $today,
                'rates' => ['EUR' => 0.73035],
            ], 200),
        ]);

        $this->artisan('exchange-rates:fetch-daily')->assertSuccessful();

        $this->assertEqualsWithDelta(4.008, ExchangeRate::getRateForDate('USD', 'ARS', $today), 0.0001);
        $this->assertEqualsWithDelta(0.73035, ExchangeRate::getRateForDate('USD', 'EUR', $today), 0.0001);
    }

    public function test_fetch_daily_skips_unchanged_rates_on_second_run(): void
    {
        $today = now()->toDateString();

        Http::fake([
            'api.bcra.gob.ar/*' => Http::response([
                'status' => 200,
                'metadata' => ['resultset' => ['count' => 1, 'offset' => 0, 'limit' => 1000]],
                'results' => [
                    [
                        'fecha' => $today,
                        'detalle' => [
                            ['codigoMoneda' => 'USD', 'tipoCotizacion' => 4.008],
                        ],
                    ],
                ],
            ], 200),
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0,
                'base' => 'USD',
                'date' => $today,
                'rates' => ['EUR' => 0.73035],
            ], 200),
        ]);

        $this->artisan('exchange-rates:fetch-daily')->assertSuccessful();
        $this->artisan('exchange-rates:fetch-daily')->assertSuccessful();

        $this->assertSame(2, ExchangeRate::query()->count());
    }
}
