<?php

namespace App\Console\Commands;

use App\Models\ExchangeRateHistory;
use App\Services\Currency\FrankfurterExchangeRateClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillFrankfurterExchangeRateHistoriesCommand extends Command
{
    protected $signature = 'exchange-rates:backfill-frankfurter
                            {--from=2000-01 : First month (YYYY-MM)}
                            {--to= : Last month inclusive (YYYY-MM); default: current calendar month}
                            {--base=USD : Base currency}
                            {--currencies=EUR : Comma-separated target currencies}
                            {--strategy=last : Monthly rate from daily quotes: last or avg}
                            {--sleep=0.5 : Seconds to sleep between API calls}
                            {--skip-existing : Skip month if all targets already stored}
                            {--dry-run : Log actions without writing}';

    protected $description = 'Backfill monthly exchange rates from Frankfurter (ECB-based, free, no API key)';

    public function handle(FrankfurterExchangeRateClient $client): int
    {
        $strategy = strtolower((string) $this->option('strategy'));

        if (! in_array($strategy, ['last', 'avg'], true))
        {
            $this->error('Invalid --strategy: use last or avg');

            return self::FAILURE;
        }

        $base = strtoupper((string) $this->option('base'));
        $targets = $this->resolveTargetCurrencies();

        if ($targets === [])
        {
            $this->error('No target currencies. Use --currencies=EUR');

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

        $this->info('Provider: Frankfurter, base: '.$base.', targets: '.implode(', ', $targets).", strategy: {$strategy}");
        $this->info("Months: {$from->format('Y-m')} … {$to->format('Y-m')}");

        $cursor = $from->copy();
        $monthsProcessed = 0;
        $rowsWritten = 0;
        $errors = 0;
        $skipped = 0;

        while ($cursor->lte($to))
        {
            $rateMonth = $cursor->copy()->startOfMonth()->toDateString();
            $fechaDesde = $rateMonth;
            $fechaHasta = $cursor->copy()->endOfMonth()->toDateString();

            if ($skipExisting && $this->monthFullyStored($base, $targets, $rateMonth))
            {
                $this->line("  skip {$rateMonth} (already complete)");
                $skipped++;
                $cursor->addMonth();
                $monthsProcessed++;

                continue;
            }

            if ($dryRun)
            {
                $this->line("  [dry-run] would fetch Frankfurter {$fechaDesde} … {$fechaHasta}");
                $cursor->addMonth();
                $monthsProcessed++;

                if ($sleepSeconds > 0)
                {
                    usleep((int) ($sleepSeconds * 1_000_000));
                }

                continue;
            }

            $result = $client->fetchQuotesForRange($fechaDesde, $fechaHasta, $base, $targets);

            if (! $result['success'])
            {
                $errors++;
                $msg = $result['error'] ?? 'Unknown error';
                $this->error("  {$rateMonth}: {$msg}");
                Log::warning('exchange_rate_histories.frankfurter_backfill_failed', [
                    'rate_month' => $rateMonth,
                    'message' => $msg,
                    'status' => $result['status'] ?? null,
                ]);

                $cursor->addMonth();
                $monthsProcessed++;

                if ($sleepSeconds > 0)
                {
                    usleep((int) ($sleepSeconds * 1_000_000));
                }

                continue;
            }

            $quotes = $result['quotes'] ?? [];
            $fetchedAt = now();
            $monthRows = 0;

            foreach ($targets as $target)
            {
                $rate = $client->resolveMonthlyRate($quotes, $target, $strategy);

                if ($rate === null)
                {
                    $this->warn("  {$rateMonth}: no {$base}/{$target} quotes returned");

                    continue;
                }

                ExchangeRateHistory::updateOrCreate(
                    [
                        'base_currency' => $base,
                        'target_currency' => $target,
                        'rate_month' => $rateMonth,
                    ],
                    [
                        'rate' => $rate,
                        'fetched_at' => $fetchedAt,
                        'provider' => 'frankfurter',
                        'payload' => [
                            'strategy' => $strategy,
                            'quote_count' => count($quotes),
                            'fecha_desde' => $fechaDesde,
                            'fecha_hasta' => $fechaHasta,
                        ],
                    ],
                );

                $monthRows++;
                $rowsWritten++;
                $this->line("  ✓ {$rateMonth} {$base}/{$target}: {$rate} ({$strategy}, ".count($quotes).' days)');
            }

            if ($monthRows === 0)
            {
                $this->warn("  {$rateMonth}: no rates stored");
            }

            $cursor->addMonth();
            $monthsProcessed++;

            if ($sleepSeconds > 0)
            {
                usleep((int) ($sleepSeconds * 1_000_000));
            }
        }

        $this->info("Done. Months iterated: {$monthsProcessed}, rows written: {$rowsWritten}, skipped: {$skipped}, errors: {$errors}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveTargetCurrencies(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $currency): string => strtoupper(trim($currency)),
            explode(',', (string) $this->option('currencies')),
        )));
    }

    /**
     * @param  array<int, string>  $targets
     */
    private function monthFullyStored(string $base, array $targets, string $rateMonth): bool
    {
        foreach ($targets as $target)
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
