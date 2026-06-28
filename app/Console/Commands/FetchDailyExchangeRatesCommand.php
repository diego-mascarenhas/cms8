<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use App\Services\Currency\BcraExchangeRateClient;
use App\Services\Currency\FrankfurterExchangeRateClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchDailyExchangeRatesCommand extends Command
{
    protected $signature = 'exchange-rates:fetch-daily
                            {--lookback-days=7 : Days to search backwards when today has no BCRA quote (weekends/holidays)}';

    protected $description = 'Fetch daily USD/ARS (BCRA) and USD/EUR (Frankfurter) into exchange_rates without duplicating unchanged values';

    public function handle(
        BcraExchangeRateClient $bcraClient,
        FrankfurterExchangeRateClient $frankfurterClient,
    ): int {
        $lookbackDays = max(1, (int) $this->option('lookback-days'));
        $today = Carbon::today()->toDateString();
        $fromDate = Carbon::today()->subDays($lookbackDays)->toDateString();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $bcraResult = $bcraClient->fetchUsdQuotesForRange($fromDate, $today);

        if (! $bcraResult['success'])
        {
            $errors++;
            $message = $bcraResult['error'] ?? 'Unknown BCRA error';
            $this->error("BCRA: {$message}");
            Log::warning('exchange_rates.daily_fetch_bcra_failed', ['message' => $message]);
        } else
        {
            $latest = $bcraClient->latestQuote($bcraResult['quotes'] ?? []);

            if ($latest === null)
            {
                $this->warn('BCRA: no USD/ARS quotes in lookback window');
            } else
            {
                $action = ExchangeRate::storeDailyIfChanged(
                    'USD',
                    'ARS',
                    $latest['fecha'],
                    $latest['rate'],
                );

                $this->recordAction($action, 'USD/ARS', $latest['fecha'], (string) $latest['rate'], $created, $updated, $skipped);
            }
        }

        $frankfurterResult = $frankfurterClient->fetchLatest('USD', ['EUR']);

        if (! $frankfurterResult['success'])
        {
            $errors++;
            $message = $frankfurterResult['error'] ?? 'Unknown Frankfurter error';
            $this->error("Frankfurter: {$message}");
            Log::warning('exchange_rates.daily_fetch_frankfurter_failed', ['message' => $message]);
        } else
        {
            $date = $frankfurterResult['date'] ?? $today;
            $rate = $frankfurterResult['rates']['EUR'] ?? null;

            if ($rate === null)
            {
                $this->warn('Frankfurter: missing USD/EUR rate');
            } else
            {
                $action = ExchangeRate::storeDailyIfChanged(
                    'USD',
                    'EUR',
                    $date,
                    (float) $rate,
                );

                $this->recordAction($action, 'USD/EUR', $date, (string) $rate, $created, $updated, $skipped);
            }
        }

        $this->info("Done. created: {$created}, updated: {$updated}, skipped: {$skipped}, errors: {$errors}");

        return $errors > 0 && ($created + $updated + $skipped) === 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function recordAction(
        string $action,
        string $pair,
        string $date,
        string $rate,
        int &$created,
        int &$updated,
        int &$skipped,
    ): void {
        if ($action === 'created')
        {
            $created++;
            $this->line("  ✓ {$pair} {$date}: {$rate} (created)");
        } elseif ($action === 'updated')
        {
            $updated++;
            $this->line("  ✓ {$pair} {$date}: {$rate} (updated)");
        } else
        {
            $skipped++;
            $this->line("  · {$pair} {$date}: {$rate} (unchanged, skipped)");
        }
    }
}
