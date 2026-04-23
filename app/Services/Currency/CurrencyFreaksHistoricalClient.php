<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Http;

class CurrencyFreaksHistoricalClient
{
    private const HISTORICAL_URL = 'https://api.currencyfreaks.com/v2.0/rates/historical';

    public function __construct(
        private readonly string $apiKey,
    ) {}

    public static function fromConfig(): self
    {
        $key = (string) config('services.currencyfreaks.api_key', '');

        return new self($key);
    }

    /**
     * @param  array<int, string>  $symbols  Uppercase ISO codes, e.g. ['ARS','EUR']
     * @return array{success: bool, status?: int, data?: array, error?: string}
     */
    public function fetchHistorical(string $dateYmd, string $baseCurrency, array $symbols): array
    {
        if ($this->apiKey === '')
        {
            return ['success' => false, 'error' => 'CurrencyFreaks API key is not configured.'];
        }

        $baseCurrency = strtoupper($baseCurrency);
        $symbols = array_values(array_filter(array_map('strtoupper', $symbols)));

        $response = Http::timeout(45)->acceptJson()->get(self::HISTORICAL_URL, [
            'apikey' => $this->apiKey,
            'date' => $dateYmd,
            'base' => $baseCurrency,
            'symbols' => implode(',', $symbols),
        ]);

        if (! $response->successful())
        {
            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $response->body() ?: 'HTTP '.$response->status(),
            ];
        }

        $data = $response->json();

        if (! is_array($data))
        {
            return ['success' => false, 'error' => 'Invalid JSON response.'];
        }

        return ['success' => true, 'data' => $data];
    }
}
