<?php

namespace Tests\Unit\Services\Currency;

use App\Services\Currency\FrankfurterExchangeRateClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FrankfurterExchangeRateClientTest extends TestCase
{
    public function test_fetch_quotes_for_range_parses_daily_usd_eur_rates(): void
    {
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0,
                'base' => 'USD',
                'start_date' => '2011-01-03',
                'end_date' => '2011-01-31',
                'rates' => [
                    '2011-01-31' => ['EUR' => 0.73035],
                    '2011-01-28' => ['EUR' => 0.7312],
                ],
            ], 200),
        ]);

        $client = new FrankfurterExchangeRateClient;
        $result = $client->fetchQuotesForRange('2011-01-01', '2011-01-31', 'USD', ['EUR']);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['quotes']);
        $this->assertSame(0.73035, $result['quotes'][0]['rates']['EUR']);
    }

    public function test_resolve_monthly_rate_last_uses_latest_date_for_target(): void
    {
        $client = new FrankfurterExchangeRateClient;
        $quotes = [
            ['fecha' => '2011-01-28', 'rates' => ['EUR' => 0.7312]],
            ['fecha' => '2011-01-31', 'rates' => ['EUR' => 0.73035]],
        ];

        $this->assertSame(0.73035, $client->resolveMonthlyRate($quotes, 'EUR', 'last'));
    }

    public function test_fetch_quotes_for_range_returns_error_when_api_responds_with_message(): void
    {
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'message' => 'not found',
            ], 200),
        ]);

        $client = new FrankfurterExchangeRateClient;
        $result = $client->fetchQuotesForRange('1990-01-01', '1990-01-31', 'USD', ['EUR']);

        $this->assertFalse($result['success']);
        $this->assertSame('not found', $result['error']);
    }
}
