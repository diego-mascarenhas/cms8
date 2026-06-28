<?php

namespace Tests\Unit\Services\Currency;

use App\Services\Currency\BcraExchangeRateClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BcraExchangeRateClientTest extends TestCase
{
    public function test_fetch_usd_quotes_for_range_parses_daily_rates(): void
    {
        Http::fake([
            'api.bcra.gob.ar/*' => Http::response([
                'status' => 200,
                'metadata' => [
                    'resultset' => [
                        'count' => 2,
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
                    [
                        'fecha' => '2011-01-28',
                        'detalle' => [
                            ['codigoMoneda' => 'USD', 'tipoCotizacion' => 4.001],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new BcraExchangeRateClient;
        $result = $client->fetchUsdQuotesForRange('2011-01-01', '2011-01-31');

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['quotes']);
        $this->assertSame('2011-01-31', $result['quotes'][0]['fecha']);
        $this->assertSame(4.008, $result['quotes'][0]['rate']);
    }

    public function test_resolve_monthly_rate_last_uses_latest_date(): void
    {
        $client = new BcraExchangeRateClient;
        $quotes = [
            ['fecha' => '2011-01-28', 'rate' => 4.001],
            ['fecha' => '2011-01-31', 'rate' => 4.008],
        ];

        $this->assertSame(4.008, $client->resolveMonthlyRate($quotes, 'last'));
    }

    public function test_resolve_monthly_rate_avg_uses_average_of_daily_quotes(): void
    {
        $client = new BcraExchangeRateClient;
        $quotes = [
            ['fecha' => '2011-01-28', 'rate' => 4.0],
            ['fecha' => '2011-01-31', 'rate' => 4.02],
        ];

        $this->assertSame(4.01, $client->resolveMonthlyRate($quotes, 'avg'));
    }

    public function test_fetch_usd_quotes_for_range_returns_error_on_connection_failure(): void
    {
        Http::fake(function (): void
        {
            throw new ConnectionException(new \GuzzleHttp\Psr7\Request('GET', 'test'));
        });

        $client = new BcraExchangeRateClient;
        $result = $client->fetchUsdQuotesForRange('2002-01-01', '2002-01-31');

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error'] ?? '');
    }

    public function test_fetch_usd_quotes_for_range_returns_error_on_api_failure(): void
    {
        Http::fake([
            'api.bcra.gob.ar/*' => Http::response([
                'status' => 400,
                'errorMessages' => ['Invalid date range'],
            ], 200),
        ]);

        $client = new BcraExchangeRateClient;
        $result = $client->fetchUsdQuotesForRange('1990-01-01', '1990-01-31');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid date range', $result['error'] ?? '');
    }
}
