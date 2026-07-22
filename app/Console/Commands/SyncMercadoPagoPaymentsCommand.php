<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Billing\MercadoPagoPaymentSyncUpserter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SyncMercadoPagoPaymentsCommand extends Command
{
    protected $signature = 'mercadopago:sync-payments
                            {--team_id= : Sync only one team}
                            {--limit=100 : Maximum payments to upsert into payment_syncs per team}
                            {--recent-days=90 : When --from/--to are not set, only payments on or after this many days ago}
                            {--from= : Payment created from (Y-m-d)}
                            {--to= : Payment created to (Y-m-d)}
                            {--dry-run : Preview without writing}';

    protected $description = 'Sync Mercado Pago payments into payment_syncs (staging; run payment-syncs:import-mercadopago next)';

    public function __construct(
        private readonly MercadoPagoPaymentSyncUpserter $upserter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('payment_syncs'))
        {
            $this->error('Table payment_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $maxPerTeam = max(1, (int) $this->option('limit'));
        $recentDays = max(1, (int) $this->option('recent-days'));
        $dryRun = (bool) $this->option('dry-run');

        [$beginDate, $endDate] = $this->resolveDateRange(
            $this->option('from'),
            $this->option('to'),
            $recentDays,
        );

        $teams = Team::query()->with('settings');
        if ($teamId)
        {
            $teams->whereKey($teamId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Team> $teams */
        $teams = $teams->get();
        if ($teams->isEmpty())
        {
            $this->warn('No teams found for synchronization.');

            return self::SUCCESS;
        }

        $globalUpserted = 0;
        $globalSkipped = 0;
        $globalScanned = 0;

        foreach ($teams as $team)
        {
            $token = trim((string) $team->getSetting('mercadopago_access_token'));
            if ($token === '')
            {
                $this->line("Skipping team {$team->id} ({$team->name}): missing mercadopago_access_token in team settings.");

                continue;
            }

            $offset = 0;
            $pageSize = min(50, $maxPerTeam);
            $upserted = 0;
            $skipped = 0;
            $scanned = 0;

            while ($upserted < $maxPerTeam)
            {
                $query = [
                    'sort' => 'date_created',
                    'criteria' => 'desc',
                    'range' => 'date_created',
                    'begin_date' => $beginDate,
                    'end_date' => $endDate,
                    'limit' => $pageSize,
                    'offset' => $offset,
                ];

                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(30)
                    ->get('https://api.mercadopago.com/v1/payments/search', $query);

                if (! $response->successful())
                {
                    $this->error("Team {$team->id}: Mercado Pago API error HTTP {$response->status()} — {$response->body()}");

                    break;
                }

                $payload = $response->json();
                $results = is_array($payload) ? ($payload['results'] ?? []) : [];
                if (! is_array($results) || $results === [])
                {
                    break;
                }

                foreach ($results as $row)
                {
                    if ($upserted >= $maxPerTeam)
                    {
                        break 2;
                    }

                    if (! is_array($row))
                    {
                        $skipped++;

                        continue;
                    }

                    $scanned++;
                    $paymentId = trim((string) ($row['id'] ?? ''));
                    if ($paymentId === '')
                    {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun)
                    {
                        $status = (string) ($row['status'] ?? '');
                        $this->line("[dry-run] team={$team->id} payment={$paymentId} status={$status}");
                        $upserted++;

                        continue;
                    }

                    $sync = $this->upserter->upsertFromPayload((int) $team->id, $row);
                    if ($sync === null)
                    {
                        $skipped++;

                        continue;
                    }

                    $upserted++;
                }

                $paging = is_array($payload) ? ($payload['paging'] ?? []) : [];
                $total = (int) ($paging['total'] ?? 0);
                $offset += count($results);

                if ($offset >= $total || count($results) < $pageSize)
                {
                    break;
                }
            }

            $globalUpserted += $upserted;
            $globalSkipped += $skipped;
            $globalScanned += $scanned;

            $this->info("Team {$team->id} ({$team->name}): payment_syncs upserted {$upserted}, skipped {$skipped}, scanned {$scanned}".($dryRun ? ' [dry-run]' : '').'.');
        }

        $this->line('');
        $this->info("Done. Teams: {$teams->count()} | payment_syncs upserts: {$globalUpserted} | skipped: {$globalSkipped} | scanned: {$globalScanned}".($dryRun ? ' | dry-run' : ''));
        $this->line('Run payment-syncs:import-mercadopago to create payments for the finance dashboard.');

        return self::SUCCESS;
    }

    /**
     * Mercado Pago expects begin_date/end_date as NOW-XDAYS / NOW, or
     * absolute dates as yyyy-MM-dd'T'HH:mm:ss.SSSZ (not Carbon's default ISO8601).
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(mixed $from, mixed $to, int $recentDays): array
    {
        $hasFrom = is_string($from) && trim($from) !== '';
        $hasTo = is_string($to) && trim($to) !== '';

        if (! $hasFrom && ! $hasTo)
        {
            return [
                'NOW-'.$recentDays.'DAYS',
                'NOW',
            ];
        }

        $end = now()->utc()->endOfDay();
        $begin = now()->utc()->subDays($recentDays)->startOfDay();

        if ($hasFrom)
        {
            try
            {
                $begin = Carbon::parse($from)->utc()->startOfDay();
            } catch (\Throwable)
            {
            }
        }

        if ($hasTo)
        {
            try
            {
                $end = Carbon::parse($to)->utc()->endOfDay();
            } catch (\Throwable)
            {
            }
        }

        return [
            $begin->format('Y-m-d\TH:i:s.000\Z'),
            $end->format('Y-m-d\TH:i:s.000\Z'),
        ];
    }
}
