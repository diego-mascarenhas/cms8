<?php

namespace App\Console\Commands;

use App\Enums\CuenticaInboundDocumentKind;
use App\Models\Team;
use App\Services\Fiscal\Cuentica\CuenticaClientFactory;
use App\Services\Fiscal\Cuentica\CuenticaInvoiceSyncUpserter;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncCuenticaInvoicesCommand extends Command
{
    private const SETTING_BACKFILL_STATE = 'cuentica_inbound_backfill_state';

    private const SETTING_BACKFILL_COMPLETED_AT = 'cuentica_inbound_backfill_completed_at';

    protected $signature = 'cuentica:sync-invoices
                            {--team_id= : Sync only one team}
                            {--mode=auto : auto, backfill or mutable}
                            {--limit=200 : Maximum documents to sync per team and kind in this run}
                            {--recent-days= : Override recent window for mutable mode}
                            {--from= : Initial date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--kinds=sale,purchase : Comma-separated: sale, purchase}
                            {--reset-backfill : Clear saved backfill progress}
                            {--dry-run : Preview without writing}';

    protected $description = 'Pull Cuéntica sale invoices and purchase expenses into invoice_syncs';

    public function handle(
        CuenticaClientFactory $clientFactory,
        CuenticaInvoiceSyncUpserter $upserter,
    ): int {
        if (! config('fiscal.platforms.cuentica.inbound_sync.enabled', true))
        {
            $this->warn('Cuéntica inbound sync is disabled in config.');

            return self::SUCCESS;
        }

        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $mode = strtolower(trim((string) ($this->option('mode') ?? 'auto')));
        if (! in_array($mode, ['auto', 'backfill', 'mutable'], true))
        {
            $this->error("Invalid --mode={$mode}. Allowed: auto, backfill, mutable");

            return self::INVALID;
        }

        $kinds = $this->parseKinds((string) ($this->option('kinds') ?? 'sale,purchase'));
        if ($kinds === [])
        {
            $this->error('No valid kinds. Use sale, purchase or both.');

            return self::INVALID;
        }

        $maxPerTeam = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $resetBackfill = (bool) $this->option('reset-backfill');
        $recentDays = (int) ($this->option('recent-days')
            ?: config('fiscal.platforms.cuentica.inbound_sync.recent_days', 45));

        $teams = Team::query()->with('settings');
        if ($teamId)
        {
            $teams->whereKey($teamId);
        }

        $teams = $teams->get();
        if ($teams->isEmpty())
        {
            $this->warn('No teams found.');

            return self::SUCCESS;
        }

        $processedTeams = 0;
        $globalSynced = 0;

        foreach ($teams as $team)
        {
            if (! $this->teamInboundSyncEnabled($team))
            {
                $this->line("Skipping team {$team->id}: inbound sync disabled or missing cuentica_api_token.");

                continue;
            }

            $client = $clientFactory->forTeam($team);
            if ($client === null)
            {
                continue;
            }

            if ($resetBackfill)
            {
                if (! $dryRun)
                {
                    $this->resetBackfillState($team);
                }
                $this->line("Team {$team->id}: backfill state reset".($dryRun ? ' (dry-run)' : '').'.');
            }

            $effectiveMode = $this->resolveEffectiveMode($team, $mode);
            [$from, $to] = $this->resolveDateWindow($team, $effectiveMode, $recentDays);

            $synced = 0;
            foreach ($kinds as $kind)
            {
                $kindSynced = $this->syncKind(
                    $client,
                    $team,
                    $kind,
                    $from,
                    $to,
                    $maxPerTeam,
                    $dryRun,
                    $upserter,
                );

                $synced += $kindSynced;
            }

            if (! $dryRun && $effectiveMode === 'backfill' && $synced < ($maxPerTeam * max(1, count($kinds))))
            {
                $this->advanceBackfillWindow($team, $from, $to);
            }

            $processedTeams++;
            $globalSynced += $synced;

            $dryText = $dryRun ? ' [dry-run]' : '';
            $this->info("Team {$team->id} ({$team->name}) [{$effectiveMode}] {$from}→{$to}: synced {$synced} (up to {$maxPerTeam} per kind){$dryText}.");
        }

        if ($processedTeams === 0)
        {
            $this->warn('No team eligible for Cuéntica inbound sync.');

            return self::SUCCESS;
        }

        $this->info("Cuéntica inbound sync complete: teams={$processedTeams}, synced={$globalSynced}".($dryRun ? ' [dry-run]' : '').'.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, CuenticaInboundDocumentKind>
     */
    private function parseKinds(string $raw): array
    {
        $kinds = [];
        foreach (explode(',', $raw) as $part)
        {
            $part = strtolower(trim($part));
            $kind = match ($part)
            {
                'sale', 'sales', 'venta', 'ventas' => CuenticaInboundDocumentKind::Sale,
                'purchase', 'purchases', 'expense', 'expenses', 'compra', 'compras', 'gasto', 'gastos' => CuenticaInboundDocumentKind::Purchase,
                default => null,
            };

            if ($kind !== null)
            {
                $kinds[$kind->value] = $kind;
            }
        }

        return array_values($kinds);
    }

    private function teamInboundSyncEnabled(Team $team): bool
    {
        if (! (bool) $team->getSetting('cuentica_inbound_sync_enabled', true))
        {
            return false;
        }

        return trim((string) $team->getSetting('cuentica_api_token', '')) !== '';
    }

    private function resolveEffectiveMode(Team $team, string $requestedMode): string
    {
        if ($requestedMode !== 'auto')
        {
            return $requestedMode;
        }

        $completed = trim((string) $team->getSetting(self::SETTING_BACKFILL_COMPLETED_AT, ''));

        return $completed !== '' ? 'mutable' : 'backfill';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateWindow(Team $team, string $mode, int $recentDays): array
    {
        $optFrom = $this->parseYmd($this->option('from'));
        $optTo = $this->parseYmd($this->option('to'), endOfDay: true);

        if ($optFrom && $optTo)
        {
            return [$optFrom->toDateString(), $optTo->toDateString()];
        }

        if ($mode === 'mutable')
        {
            $from = ($optFrom ?? now()->subDays(max(1, $recentDays))->startOfDay())->toDateString();
            $to = ($optTo ?? now()->endOfDay())->toDateString();

            return [$from, $to];
        }

        $state = $team->getSetting(self::SETTING_BACKFILL_STATE, null);
        $defaultStart = (string) config('fiscal.platforms.cuentica.inbound_sync.backfill_start', '2020-01-01');

        if (is_array($state) && filled($state['window_start'] ?? null))
        {
            $from = Carbon::parse((string) $state['window_start'])->startOfMonth();
        } else
        {
            $from = Carbon::parse($defaultStart)->startOfMonth();
        }

        $to = $from->copy()->endOfMonth();
        $now = now()->endOfDay();
        if ($to->gt($now))
        {
            $to = $now;
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    private function syncKind(
        $client,
        Team $team,
        CuenticaInboundDocumentKind $kind,
        string $from,
        string $to,
        int $limit,
        bool $dryRun,
        CuenticaInvoiceSyncUpserter $upserter,
    ): int {
        $pageSize = max(1, (int) config('fiscal.platforms.cuentica.inbound_sync.page_size', 50));
        $page = 1;
        $synced = 0;

        while ($synced < $limit)
        {
            $filters = [
                'page' => $page,
                'page_size' => min($pageSize, $limit - $synced),
                'initial_date' => $from,
                'end_date' => $to,
                'sort' => 'date:asc',
            ];

            if ($kind === CuenticaInboundDocumentKind::Sale)
            {
                $rows = $client->listInvoices($filters);
            } else
            {
                $filters['draft'] = 'false';
                $rows = $client->listExpenses($filters);
            }

            if ($rows === [])
            {
                break;
            }

            foreach ($rows as $row)
            {
                if ($synced >= $limit || ! is_array($row))
                {
                    break;
                }

                if (! $dryRun)
                {
                    if ($kind === CuenticaInboundDocumentKind::Sale)
                    {
                        $upserter->upsertSale($team->id, $row);
                    } else
                    {
                        $upserter->upsertPurchase($team->id, $row);
                    }
                }

                $synced++;
            }

            if (count($rows) < $filters['page_size'])
            {
                break;
            }

            $page++;
        }

        return $synced;
    }

    private function advanceBackfillWindow(Team $team, string $from, string $to): void
    {
        $windowStart = Carbon::parse($from)->startOfMonth();
        $globalEnd = now()->endOfDay();
        $nextStart = $windowStart->copy()->addMonthNoOverflow()->startOfMonth();

        if ($nextStart->gt($globalEnd))
        {
            $team->setSetting(self::SETTING_BACKFILL_STATE, null, [
                'type' => 'json',
                'group' => 'cuentica',
            ]);
            $team->setSetting(self::SETTING_BACKFILL_COMPLETED_AT, now()->toDateTimeString(), [
                'type' => 'string',
                'group' => 'cuentica',
            ]);

            return;
        }

        $team->setSetting(self::SETTING_BACKFILL_STATE, [
            'window_start' => $nextStart->toDateString(),
        ], [
            'type' => 'json',
            'group' => 'cuentica',
        ]);
    }

    private function resetBackfillState(Team $team): void
    {
        $team->setSetting(self::SETTING_BACKFILL_STATE, null, [
            'type' => 'json',
            'group' => 'cuentica',
        ]);
        $team->setSetting(self::SETTING_BACKFILL_COMPLETED_AT, '', [
            'type' => 'string',
            'group' => 'cuentica',
        ]);
    }

    private function parseYmd(mixed $value, bool $endOfDay = false): ?Carbon
    {
        if (! is_string($value) || trim($value) === '')
        {
            return null;
        }

        try
        {
            $date = Carbon::parse($value);

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable)
        {
            $this->warn("Ignoring invalid date: {$value}");

            return null;
        }
    }
}
