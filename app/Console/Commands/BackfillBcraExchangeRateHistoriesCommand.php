<?php

namespace App\Console\Commands;

use App\Models\ExchangeRateHistory;
use App\Services\Currency\BcraExchangeRateClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillBcraExchangeRateHistoriesCommand extends Command
{
    protected $signature = 'exchange-rates:backfill-bcra
                            {--from=2011-01 : First month (YYYY-MM)}
                            {--to= : Last month inclusive (YYYY-MM); default: current calendar month}
                            {--strategy=last : Monthly rate from daily quotes: last or avg}
                            {--sleep=0.5 : Seconds to sleep between API calls}
                            {--skip-existing : Skip month if USD/ARS already stored}
                            {--dry-run : Log actions without writing}';

    protected $description = 'Backfill monthly USD/ARS exchange rates from BCRA official statistics (one API call per month)';

    public function handle(BcraExchangeRateClient $client): int
    {
        $strategy = strtolower((string) $this->option('strategy'));

        if (! in_array($strategy, ['last', 'avg'], true))
        {
            $this->error('Invalid --strategy: use last or avg');

            return self::FAILURE;
        }

        $fromRaw = Carbon::createFromFormat('Y-m', (string) $this->option('from'));
        if ($fromRaw === false)
        {
            $this->error('Invalid --from: use YYYY-MM (e.g. 2011-01)');

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

        $this->info("Provider: BCRA (USD/ARS official), strategy: {$strategy}");
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

            if ($skipExisting && $this->monthStored($rateMonth))
            {
                $this->line("  skip {$rateMonth} (already stored)");
                $skipped++;
                $cursor->addMonth();
                $monthsProcessed++;

                continue;
            }

            if ($dryRun)
            {
                $this->line("  [dry-run] would fetch BCRA {$fechaDesde} … {$fechaHasta}");
                $cursor->addMonth();
                $monthsProcessed++;

                if ($sleepSeconds > 0)
                {
                    usleep((int) ($sleepSeconds * 1_000_000));
                }

                continue;
            }

            $result = $client->fetchUsdQuotesForRange($fechaDesde, $fechaHasta);

            if (! $result['success'])
            {
                $errors++;
                $msg = $result['error'] ?? 'Unknown error';
                $this->error("  {$rateMonth}: {$msg}");
                Log::warning('exchange_rate_histories.bcra_backfill_failed', [
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
            $rate = $client->resolveMonthlyRate($quotes, $strategy);

            if ($rate === null)
            {
                $this->warn("  {$rateMonth}: no USD quotes returned");
                $cursor->addMonth();
                $monthsProcessed++;

                if ($sleepSeconds > 0)
                {
                    usleep((int) ($sleepSeconds * 1_000_000));
                }

                continue;
            }

            ExchangeRateHistory::updateOrCreate(
                [
                    'base_currency' => 'USD',
                    'target_currency' => 'ARS',
                    'rate_month' => $rateMonth,
                ],
                [
                    'rate' => $rate,
                    'fetched_at' => now(),
                    'provider' => 'bcra',
                    'payload' => [
                        'strategy' => $strategy,
                        'quote_count' => count($quotes),
                        'fecha_desde' => $fechaDesde,
                        'fecha_hasta' => $fechaHasta,
                    ],
                ],
            );

            $rowsWritten++;
            $this->line("  ✓ {$rateMonth} USD/ARS: {$rate} ({$strategy}, ".count($quotes).' days)');

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

    private function monthStored(string $rateMonth): bool
    {
        return ExchangeRateHistory::query()
            ->where('base_currency', 'USD')
            ->where('target_currency', 'ARS')
            ->whereDate('rate_month', $rateMonth)
            ->exists();
    }
}
