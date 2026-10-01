<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackfillDailyExchangeRatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_stores_each_daily_quote(): void
    {
        Http::fake([
            'api.bcra.gob.ar/*' => Http::response([
                'status' => 200,
                'metadata' => ['resultset' => ['count' => 2, 'offset' => 0, 'limit' => 1000]],
                'results' => [
                    ['fecha' => '2026-07-24', 'detalle' => [['codigoMoneda' => 'USD', 'tipoCotizacion' => 1500]]],
                    ['fecha' => '2026-07-27', 'detalle' => [['codigoMoneda' => 'USD', 'tipoCotizacion' => 1510]]],
                ],
            ]),
            'api.frankfurter.dev/*' => Http::response([
                'base' => 'USD',
                'rates' => [
                    '2026-07-24' => ['EUR' => 0.86],
                    '2026-07-27' => ['EUR' => 0.87],
                ],
            ]),
        ]);

        $this->artisan('exchange-rates:backfill-daily', [
            '--from' => '2026-07-24',
            '--to' => '2026-07-27',
        ])->assertSuccessful();

        $this->assertSame('1500.00000000', ExchangeRate::query()
            ->where('target_currency', 'ARS')
            ->whereDate('date', '2026-07-24')
            ->value('rate'));
        $this->assertSame('0.87000000', ExchangeRate::query()
            ->where('target_currency', 'EUR')
            ->whereDate('date', '2026-07-27')
            ->value('rate'));
        $this->assertEqualsWithDelta(
            0.86 / 1500,
            ExchangeRate::rateOnOrBeforeDate('ARS', 'EUR', '2026-07-26'),
            0.0000001,
        );
    }
}
