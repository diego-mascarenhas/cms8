<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Http;

class FrankfurterExchangeRateClient
{
    public function __construct(
        private readonly string $baseUrl = 'https://api.frankfurter.dev',
        private readonly int $timeoutSeconds = 45,
    ) {}

    public static function fromConfig(): self
    {
        $baseUrl = rtrim((string) config('services.frankfurter.base_url', 'https://api.frankfurter.dev'), '/');
        $timeout = max(1, (int) config('services.frankfurter.timeout_seconds', 45));

        return new self($baseUrl, $timeout);
    }

    /**
     * @param  array<int, string>  $symbols
     * @return array{success: bool, status?: int, quotes?: array<int, array{fecha: string, rates: array<string, float>}>, error?: string, payload?: array}
     */
    public function fetchQuotesForRange(string $fromYmd, string $toYmd, string $baseCurrency, array $symbols): array
    {
        $baseCurrency = strtoupper($baseCurrency);
        $symbols = array_values(array_filter(array_map(
            static fn (string $symbol): string => strtoupper(trim($symbol)),
            $symbols,
        )));

        if ($symbols === [])
        {
            return ['success' => false, 'error' => 'No target currencies provided.'];
        }

        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get("{$this->baseUrl}/v1/{$fromYmd}..{$toYmd}", [
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

        if (isset($data['message']) && ! isset($data['rates']))
        {
            return ['success' => false, 'error' => (string) $data['message']];
        }

        $ratesByDate = is_array($data['rates'] ?? null) ? $data['rates'] : [];
        $quotes = [];

        foreach ($ratesByDate as $fecha => $rates)
        {
            if (! is_string($fecha) || ! is_array($rates))
            {
                continue;
            }

            $parsedRates = [];

            foreach ($symbols as $symbol)
            {
                if (! isset($rates[$symbol]) || ! is_numeric($rates[$symbol]))
                {
                    continue;
                }

                $parsedRates[$symbol] = (float) $rates[$symbol];
            }

            if ($parsedRates === [])
            {
                continue;
            }

            $quotes[] = [
                'fecha' => $fecha,
                'rates' => $parsedRates,
            ];
        }

        return [
            'success' => true,
            'quotes' => $quotes,
            'payload' => $data,
        ];
    }

    /**
     * @param  array<int, array{fecha: string, rates: array<string, float>}>  $quotes
     */
    public function resolveMonthlyRate(array $quotes, string $targetCurrency, string $strategy = 'last'): ?float
    {
        $targetCurrency = strtoupper($targetCurrency);
        $dailyRates = [];

        foreach ($quotes as $quote)
        {
            $rate = $quote['rates'][$targetCurrency] ?? null;

            if ($rate !== null && $rate > 0)
            {
                $dailyRates[] = [
                    'fecha' => $quote['fecha'],
                    'rate' => $rate,
                ];
            }
        }

        if ($dailyRates === [])
        {
            return null;
        }

        if ($strategy === 'avg')
        {
            $sum = array_sum(array_column($dailyRates, 'rate'));

            return round($sum / count($dailyRates), 8);
        }

        usort($dailyRates, static fn (array $a, array $b): int => strcmp($b['fecha'], $a['fecha']));

        return round((float) $dailyRates[0]['rate'], 8);
    }
}
