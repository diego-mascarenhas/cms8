<?php

namespace App\Console\Commands;

use App\Models\ExchangeRateHistory;
use App\Services\Currency\CurrencyFreaksHistoricalClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillExchangeRateHistoriesCommand extends Command
{
    protected $signature = 'exchange-rates:backfill-monthly
                            {--from=2000-01 : First month (YYYY-MM), first day used as API date}
                            {--to= : Last month inclusive (YYYY-MM); default: current calendar month}
                            {--base=USD : Base currency (CurrencyFreaks free tier uses USD)}
                            {--currencies= : Overrides CURRENCYFREAKS_TARGET_CURRENCIES (comma-separated)}
                            {--sleep=1 : Seconds to sleep between API calls}
                            {--skip-existing : Skip month if all targets already stored}
                            {--dry-run : Log actions without writing}';

    protected $description = 'Backfill monthly USD-based exchange rates via CurrencyFreaks historical API (one API call per month)';

    public function handle(): int
    {
        $client = CurrencyFreaksHistoricalClient::fromConfig();
        $base = strtoupper((string) $this->option('base'));
        $symbols = $this->resolveTargetCurrencies();

        if ($symbols === [])
        {
            $this->error('No target currencies. Set CURRENCYFREAKS_TARGET_CURRENCIES or use --currencies=');

            return self::FAILURE;
        }

        $fromRaw = Carbon::createFromFormat('Y-m', (string) $this->option('from'));
        if ($fromRaw === false)
        {
            $this->error('Invalid --from: use YYYY-MM (e.g. 2000-01)');

            return self::FAILURE;
        }
        $from = $fromRaw->copy()->startOfMonth();

        $toOption = $this->option('to');
        if ($toOption !== null && $toOption !== '')
        {
            $toRaw = Carbon::createFromFormat('Y-m', (string) $toOption);
            if ($toRaw === false)
            {
                $this->error('Invalid --to: use YYYY-MM');

                return self::FAILURE;
            }
            $to = $toRaw->copy()->startOfMonth();
        } else
        {
            $to = Carbon::now()->startOfMonth();
        }

        if ($from->gt($to))
        {
            $this->error('--from must be on or before --to');

            return self::FAILURE;
        }

        $sleepSeconds = max(0, (float) $this->option('sleep'));
        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');

        $this->info("Base: {$base}, targets: ".implode(', ', $symbols));
        $this->info("Months: {$from->format('Y-m')} … {$to->format('Y-m')}");

        $cursor = $from->copy();
        $monthsProcessed = 0;
        $rowsWritten = 0;
        $errors = 0;

        while ($cursor->lte($to))
        {
            $rateMonth = $cursor->copy()->startOfMonth()->toDateString();
            $apiDate = $rateMonth;

            if ($skipExisting && $this->monthFullyStored($base, $symbols, $rateMonth))
            {
                $this->line("  skip {$rateMonth} (already complete)");
                $cursor->addMonth();
                $monthsProcessed++;

                continue;
            }

            if ($dryRun)
            {
                $this->line("  [dry-run] would fetch historical {$apiDate}");
                $cursor->addMonth();
                $monthsProcessed++;

                if ($sleepSeconds > 0)
                {
                    usleep((int) ($sleepSeconds * 1_000_000));
                }

                continue;
            }

            $result = $client->fetchHistorical($apiDate, $base, $symbols);

            if (! $result['success'])
            {
                $errors++;
                $msg = $result['error'] ?? 'Unknown error';
                $this->error("  {$apiDate}: {$msg}");
                Log::warning('exchange_rate_histories.backfill_failed', [
                    'date' => $apiDate,
                    'message' => $msg,
                    'status' => $result['status'] ?? null,
                ]);

                if (isset($result['status']) && in_array((int) $result['status'], [401, 402, 403], true))
                {
                    $this->warn('Historical endpoint may require a paid CurrencyFreaks plan. Stopping.');
                    break;
                }

                $cursor->addMonth();
                $monthsProcessed++;

                if ($sleepSeconds > 0)
                {
                    usleep((int) ($sleepSeconds * 1_000_000));
                }

                continue;
            }

            $data = $result['data'];
            $rates = is_array($data['rates'] ?? null) ? $data['rates'] : [];
            $fetchedAt = now();

            foreach ($symbols as $target)
            {
                if (! isset($rates[$target]))
                {
                    $this->warn("  {$apiDate}: missing rate for {$target}");

                    continue;
                }

                ExchangeRateHistory::updateOrCreate(
                    [
                        'base_currency' => $base,
                        'target_currency' => $target,
                        'rate_month' => $rateMonth,
                    ],
                    [
                        'rate' => $rates[$target],
                        'fetched_at' => $fetchedAt,
                        'provider' => 'currencyfreaks',
                        'payload' => $data,
                    ],
                );
                $rowsWritten++;
                $this->line("  ✓ {$rateMonth} {$base}/{$target}: {$rates[$target]}");
            }

            $cursor->addMonth();
            $monthsProcessed++;

            if ($sleepSeconds > 0)
            {
                usleep((int) ($sleepSeconds * 1_000_000));
            }
        }

        $this->info("Done. Months iterated: {$monthsProcessed}, rows written: {$rowsWritten}, errors: {$errors}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveTargetCurrencies(): array
    {
        $raw = $this->option('currencies');
        if ($raw !== null && $raw !== '')
        {
            return array_values(array_filter(array_map(
                static fn (string $c): string => strtoupper(trim($c)),
                explode(',', (string) $raw),
            )));
        }

        $configured = config('services.currencyfreaks.target_currencies', 'ARS,EUR');
        $csv = is_string($configured) ? $configured : implode(',', (array) $configured);

        return array_values(array_filter(array_map(
            static fn (string $c): string => strtoupper(trim($c)),
            explode(',', $csv),
        )));
    }

    /**
     * @param  array<int, string>  $symbols
     */
    private function monthFullyStored(string $base, array $symbols, string $rateMonth): bool
    {
        foreach ($symbols as $target)
        {
            $exists = ExchangeRateHistory::query()
                ->where('base_currency', $base)
                ->where('target_currency', $target)
                ->whereDate('rate_month', $rateMonth)
                ->exists();

            if (! $exists)
            {
                return false;
            }
        }

        return true;
    }
}
