<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Http;

class BcraExchangeRateClient
{
    private const BASE_URL = 'https://api.bcra.gob.ar/estadisticascambiarias/v1.0/Cotizaciones';

    private const PAGE_LIMIT = 1000;

    public function __construct(
        private readonly int $timeoutSeconds = 45,
    ) {}

    public static function fromConfig(): self
    {
        $timeout = (int) config('services.bcra.timeout_seconds', 45);

        return new self(max(1, $timeout));
    }

    /**
     * @return array{success: bool, status?: int, quotes?: array<int, array{fecha: string, rate: float}>, error?: string}
     */
    public function fetchUsdQuotesForRange(string $fromYmd, string $toYmd): array
    {
        $quotes = [];
        $offset = 0;

        do
        {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get(self::BASE_URL.'/USD', [
                    'fechaDesde' => $fromYmd,
                    'fechaHasta' => $toYmd,
                    'limit' => self::PAGE_LIMIT,
                    'offset' => $offset,
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

            if ((int) ($data['status'] ?? 0) !== 200)
            {
                $messages = is_array($data['errorMessages'] ?? null)
                    ? implode('; ', $data['errorMessages'])
                    : 'BCRA API error';

                return [
                    'success' => false,
                    'status' => (int) ($data['status'] ?? 0),
                    'error' => $messages,
                ];
            }

            $results = is_array($data['results'] ?? null) ? $data['results'] : [];
            $totalCount = (int) ($data['metadata']['resultset']['count'] ?? count($results));

            foreach ($results as $row)
            {
                if (! is_array($row))
                {
                    continue;
                }

                $fecha = (string) ($row['fecha'] ?? '');
                $rate = $this->extractUsdRate($row);

                if ($fecha === '' || $rate === null)
                {
                    continue;
                }

                if (! isset($quotes[$fecha]))
                {
                    $quotes[$fecha] = [
                        'fecha' => $fecha,
                        'rate' => $rate,
                    ];
                }
            }

            $offset += self::PAGE_LIMIT;
        } while ($offset < $totalCount);

        return [
            'success' => true,
            'quotes' => array_values($quotes),
        ];
    }

    /**
     * @param  array<int, array{fecha: string, rate: float}>  $quotes
     */
    public function resolveMonthlyRate(array $quotes, string $strategy = 'last'): ?float
    {
        if ($quotes === [])
        {
            return null;
        }

        if ($strategy === 'avg')
        {
            $sum = array_sum(array_column($quotes, 'rate'));

            return round($sum / count($quotes), 8);
        }

        usort($quotes, static fn (array $a, array $b): int => strcmp($b['fecha'], $a['fecha']));

        return round((float) $quotes[0]['rate'], 8);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function extractUsdRate(array $row): ?float
    {
        $detalle = is_array($row['detalle'] ?? null) ? $row['detalle'] : [];

        foreach ($detalle as $entry)
        {
            if (! is_array($entry))
            {
                continue;
            }

            if (($entry['codigoMoneda'] ?? null) !== 'USD')
            {
                continue;
            }

            $rate = $entry['tipoCotizacion'] ?? null;

            if ($rate !== null && is_numeric($rate) && (float) $rate > 0)
            {
                return (float) $rate;
            }
        }

        return null;
    }
}
