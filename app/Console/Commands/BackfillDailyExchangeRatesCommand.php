<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use App\Services\Currency\BcraExchangeRateClient;
use App\Services\Currency\FrankfurterExchangeRateClient;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillDailyExchangeRatesCommand extends Command
{
    protected $signature = 'exchange-rates:backfill-daily
                            {--from= : First date (Y-m-d). Default: the day after the latest USD/ARS quote}
                            {--to= : Last date (Y-m-d). Default: today}
                            {--dry-run : Preview without writing}';

    protected $description = 'Backfill daily USD/ARS (BCRA) and USD/EUR (Frankfurter) quotes so each invoice date has its own rate';

    public function handle(
        BcraExchangeRateClient $bcraClient,
        FrankfurterExchangeRateClient $frankfurterClient,
    ): int {
        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'))->startOfDay()
            : Carbon::today();

        if ($this->option('from'))
        {
            $from = Carbon::parse((string) $this->option('from'))->startOfDay();
        } else
        {
            $lastArs = ExchangeRate::query()
                ->where('base_currency', 'USD')
                ->where('target_currency', 'ARS')
                ->max('date');

            $from = $lastArs
                ? Carbon::parse($lastArs)->addDay()->startOfDay()
                : $to->copy()->startOfYear();
        }

        if ($from->greaterThan($to))
        {
            $this->info('Daily rates are already current through '.$to->toDateString().'.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info($from->toDateString().' … '.$to->toDateString().($dryRun ? ' (dry-run)' : ''));

        $errors = 0;
        $errors += $this->storeBcra($bcraClient, $from, $to, $dryRun);
        $errors += $this->storeFrankfurter($frankfurterClient, $from, $to, $dryRun);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function storeBcra(BcraExchangeRateClient $client, Carbon $from, Carbon $to, bool $dryRun): int
    {
        $result = $client->fetchUsdQuotesForRange($from->toDateString(), $to->toDateString());
        if (! $result['success'])
        {
            $this->error('BCRA: '.($result['error'] ?? 'request failed'));

            return 1;
        }

        $stored = 0;
        foreach ($result['quotes'] ?? [] as $quote)
        {
            $stored += $this->store('USD', 'ARS', $quote['fecha'], (float) $quote['rate'], $dryRun);
        }

        $this->line('BCRA USD/ARS: '.count($result['quotes'] ?? []).' quotes, '.$stored.' written');

        return 0;
    }

    private function storeFrankfurter(FrankfurterExchangeRateClient $client, Carbon $from, Carbon $to, bool $dryRun): int
    {
        $result = $client->fetchQuotesForRange($from->toDateString(), $to->toDateString(), 'USD', ['EUR']);
        if (! $result['success'])
        {
            $this->error('Frankfurter: '.($result['error'] ?? 'request failed'));

            return 1;
        }

        $stored = 0;
        foreach ($result['quotes'] ?? [] as $quote)
        {
            $rate = $quote['rates']['EUR'] ?? null;
            if ($rate === null)
            {
                continue;
            }

            $stored += $this->store('USD', 'EUR', $quote['fecha'], (float) $rate, $dryRun);
        }

        $this->line('Frankfurter USD/EUR: '.count($result['quotes'] ?? []).' quotes, '.$stored.' written');

        return 0;
    }

    private function store(string $base, string $target, string $date, float $rate, bool $dryRun): int
    {
        if ($dryRun)
        {
            $this->line("[dry-run] {$base}/{$target} {$date}: {$rate}");

            return 0;
        }

        $action = ExchangeRate::storeDailyIfChanged($base, $target, $date, $rate);

        return $action === 'skipped' ? 0 : 1;
    }
}
