<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchExchangeRates extends Command
{
    protected $signature = 'exchange-rates:fetch
                            {--base=USD : Base currency code}
                            {--currencies=ARS,EUR : Comma-separated list of target currencies}';

    protected $description = 'Fetch exchange rates from CurrencyFreaks API';

    public function handle()
    {
        $apiKey = config('services.currencyfreaks.api_key');

        if (empty($apiKey)) {
            $this->error('CurrencyFreaks API key not configured in .env');
            return 1;
        }

        $baseCurrency = strtoupper($this->option('base'));
        $targetCurrencies = array_map('trim', explode(',', strtoupper($this->option('currencies'))));

        try {
            $this->info("Fetching rates: {$baseCurrency} -> " . implode(', ', $targetCurrencies));

            $url = "https://api.currencyfreaks.com/v2.0/rates/latest";
            $response = Http::timeout(30)->get($url, [
                'apikey' => $apiKey,
                'base' => $baseCurrency,
                'symbols' => implode(',', $targetCurrencies),
            ]);

            if (!$response->successful()) {
                $this->error("API Error: " . $response->status());
                return 1;
            }

            $data = $response->json();
            $date = isset($data['date']) ? Carbon::parse($data['date'])->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            $fetchedAt = Carbon::now();

            $count = 0;
            foreach ($targetCurrencies as $target) {
                if (!isset($data['rates'][$target])) continue;

                ExchangeRate::updateOrCreate(
                    [
                        'base_currency' => $baseCurrency,
                        'target_currency' => $target,
                        'date' => $date,
                    ],
                    [
                        'rate' => $data['rates'][$target],
                        'fetched_at' => $fetchedAt,
                    ]
                );

                $this->line("  ✓ {$baseCurrency}/{$target}: {$data['rates'][$target]}");
                $count++;
            }

            $this->info("✓ Stored {$count} exchange rates");
            return 0;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('Exchange rates fetch error', ['message' => $e->getMessage()]);
            return 1;
        }
    }
}
