<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\DemoDataService;
use Database\Seeders\DemoMailCampaignData;
use Illuminate\Console\Command;

class StartCommand extends Command
{
    protected $signature = 'start
                            {--fresh : Fresh install (migrate:fresh + seed), then optionally demo (non-interactive: use with --demo)}
                            {--demo : After fresh, or alone: load demo data (non-interactive)}
                            {--stores : Import Pedimos Facil stores into teams (non-interactive)}
                            {--clone-catalog : Clone products catalog (stores, categories, products) between teams}
                            {--google-sync : Queue Google contacts + calendar sync for all Google accounts (or --account_id)}
                            {--google-inspect : Inspect Google sync status (all teams or --team_id)}
                            {--exchange-rates-backfill : Backfill monthly exchange rates (BCRA USD/ARS + Frankfurter USD/EUR)}
                            {--exchange-rates-from=2000-01 : Start month (YYYY-MM) for --exchange-rates-backfill}
                            {--exchange-rates-to= : End month (YYYY-MM) for --exchange-rates-backfill; default current month}
                            {--account_id= : Account ID for --google-sync}
                            {--team_id= : Team ID filter for --google-inspect}
                            {--limit=200 : Row limit for --google-inspect}
                            {--source-team= : Source team ID for --clone-catalog (optional; skips prompts if both IDs given)}
                            {--target-team= : Target team ID for --clone-catalog}';

    protected $description = 'Humano setup: fresh install (migrate:fresh --seed) then choose to load demo data';

    private const OPT_FRESH = 'Fresh install (migrate:fresh + seed)';

    private const OPT_DEMO_ONLY = 'Load demo data only (requires DB already seeded)';

    private const OPT_CHAT = 'Chat (conversar con el asistente en terminal; podés indicar teléfono de cliente)';

    private const OPT_STORES = 'Import stores (Pedimos Facil -> Teams)';

    private const OPT_CLONE_CATALOG = 'Clone catalog (stores, categories, products → another team)';

    private const OPT_GOOGLE_SYNC = 'Google: queue contacts + calendar sync';

    private const OPT_GOOGLE_INSPECT = 'Google: inspect sync status (all teams)';

    private const OPT_EXCHANGE_RATES_BACKFILL = 'Exchange rates: backfill monthly (BCRA USD/ARS + Frankfurter USD/EUR)';

    private const OPT_EXIT = 'Exit';

    public function handle(): int
    {
        $fresh = $this->option('fresh');
        $demo = $this->option('demo');
        $stores = $this->option('stores');
        $cloneCatalog = $this->option('clone-catalog');
        $googleSync = $this->option('google-sync');
        $googleInspect = $this->option('google-inspect');
        $exchangeRatesBackfill = $this->option('exchange-rates-backfill');

        if ($fresh)
        {
            $this->runFreshInstall();
            if ($demo || $this->confirm('Load demo data?', false))
            {
                $this->runDemo();
            }
            $this->info('Done.');

            return self::SUCCESS;
        }

        if ($demo)
        {
            $this->runDemo();
            $this->info('Done.');

            return self::SUCCESS;
        }

        if ($stores)
        {
            $this->runStoresImport();
            $this->info('Done.');

            return self::SUCCESS;
        }

        if ($cloneCatalog)
        {
            $this->runCloneCatalog();
            $this->info('Done.');

            return self::SUCCESS;
        }

        if ($googleSync)
        {
            $this->runGoogleSyncData();
            $this->info('Done.');

            return self::SUCCESS;
        }

        if ($googleInspect)
        {
            $this->runGoogleInspect();
            $this->info('Done.');

            return self::SUCCESS;
        }

        if ($exchangeRatesBackfill)
        {
            $this->runExchangeRatesBackfill(prompt: false);
            $this->info('Done.');

            return self::SUCCESS;
        }

        $this->info('Humano — Setup');
        $this->newLine();

        while (true)
        {
            $choice = $this->choice(
                'What do you want to do?',
                [
                    self::OPT_FRESH,
                    self::OPT_DEMO_ONLY,
                    self::OPT_CHAT,
                    self::OPT_STORES,
                    self::OPT_CLONE_CATALOG,
                    self::OPT_GOOGLE_SYNC,
                    self::OPT_GOOGLE_INSPECT,
                    self::OPT_EXCHANGE_RATES_BACKFILL,
                    self::OPT_EXIT,
                ],
                self::OPT_EXIT,
            );

            if ($choice === self::OPT_EXIT)
            {
                $this->info('Bye.');
                break;
            }

            if ($choice === self::OPT_FRESH)
            {
                $this->runFreshInstall();
                if ($this->confirm('Load demo data?', false))
                {
                    $this->runDemo();
                }
            } elseif ($choice === self::OPT_DEMO_ONLY)
            {
                $this->runDemo();
            } elseif ($choice === self::OPT_CHAT)
            {
                $this->runChatSimulate();
            } elseif ($choice === self::OPT_STORES)
            {
                $this->runStoresImport();
            } elseif ($choice === self::OPT_CLONE_CATALOG)
            {
                $this->runCloneCatalog();
            } elseif ($choice === self::OPT_GOOGLE_SYNC)
            {
                $this->runGoogleSyncData();
            } elseif ($choice === self::OPT_GOOGLE_INSPECT)
            {
                $this->runGoogleInspect();
            } elseif ($choice === self::OPT_EXCHANGE_RATES_BACKFILL)
            {
                $this->runExchangeRatesBackfill(prompt: true);
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function runChatSimulate(): void
    {
        $phoneInput = $this->ask('Teléfono del cliente a simular (solo dígitos; vacío = modo genérico sin número)');
        $phoneInput = $phoneInput !== null ? trim((string) $phoneInput) : '';
        $digits = preg_replace('/\D/', '', $phoneInput);

        if ($digits !== '')
        {
            $this->call('chat:simulate', ['--phone' => $digits]);
        } else
        {
            $this->call('chat:simulate');
        }
    }

    private function runFreshInstall(): void
    {
        $this->info('Running migrate:fresh --seed...');
        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
    }

    private function runDemo(): int
    {
        $this->info('Loading demo data...');
        $team = Team::withoutGlobalScopes()
            ->where('name', 'Demo')
            ->first();

        if (! $team)
        {
            $this->warn('Demo team not found. Run database seed first (or: php artisan db:seed).');

            return self::FAILURE;
        }

        DemoDataService::createClientsAndProjects($team->id, $this);
        DemoMailCampaignData::seed($team, $this);

        return self::SUCCESS;
    }

    private function runStoresImport(): void
    {
        $this->info('Importing Pedimos Facil stores into teams...');
        $this->call('import:interactive', ['--stores' => true]);
    }

    private function runCloneCatalog(): void
    {
        $this->info('Clone products catalog (stores, categories, products)');

        $sourceOpt = $this->option('source-team');
        $targetOpt = $this->option('target-team');

        if ($sourceOpt !== null && $sourceOpt !== '' && $targetOpt !== null && $targetOpt !== '')
        {
            $sourceId = (int) $sourceOpt;
            $targetId = (int) $targetOpt;
        } else
        {
            $sourceId = (int) $this->ask('Source team ID');
            $targetId = (int) $this->ask('Target team ID');
        }

        if ($sourceId < 1 || $targetId < 1)
        {
            $this->error('Invalid team IDs.');

            return;
        }

        if ($sourceId === $targetId)
        {
            $this->error('Source and target team must be different.');

            return;
        }

        if ($this->confirm('Dry run first (count only, no database writes)?', false))
        {
            $this->call('team:clone-catalog', [
                'source_team_id' => $sourceId,
                'target_team_id' => $targetId,
                '--dry-run' => true,
            ]);
            if (! $this->confirm('Proceed with the real clone?', true))
            {
                return;
            }
        }

        $this->call('team:clone-catalog', [
            'source_team_id' => $sourceId,
            'target_team_id' => $targetId,
        ]);
    }

    private function runGoogleSyncData(): void
    {
        $this->info('Queueing Google contacts + calendar sync...');

        $accountIdOpt = $this->option('account_id');

        if ($accountIdOpt !== null && $accountIdOpt !== '')
        {
            $this->call('google:sync-data', [
                '--account_id' => (int) $accountIdOpt,
            ]);

            return;
        }

        $useAccountFilter = $this->confirm('Filter by one Google external account ID?', false);

        if ($useAccountFilter)
        {
            $accountId = (int) $this->ask('Google external account ID');
            if ($accountId > 0)
            {
                $this->call('google:sync-data', ['--account_id' => $accountId]);

                return;
            }
            $this->warn('Invalid account ID. Running for all Google accounts.');
        }

        $this->call('google:sync-data');
    }

    private function runGoogleInspect(): void
    {
        $this->info('Inspecting Google sync status...');

        $teamIdOpt = $this->option('team_id');
        $limitOpt = (int) ($this->option('limit') ?? 200);
        $limit = $limitOpt > 0 ? $limitOpt : 200;

        if ($teamIdOpt !== null && $teamIdOpt !== '')
        {
            $this->call('google:inspect-sync', [
                '--team_id' => (int) $teamIdOpt,
                '--limit' => $limit,
            ]);

            return;
        }

        $filterByTeam = $this->confirm('Filter inspect by team ID?', false);
        if ($filterByTeam)
        {
            $teamId = (int) $this->ask('Team ID');
            if ($teamId > 0)
            {
                $this->call('google:inspect-sync', [
                    '--team_id' => $teamId,
                    '--limit' => $limit,
                ]);

                return;
            }
            $this->warn('Invalid team ID. Showing all teams.');
        }

        $this->call('google:inspect-sync', ['--limit' => $limit]);
    }

    private function runExchangeRatesBackfill(bool $prompt = false): void
    {
        $fromOpt = (string) ($this->option('exchange-rates-from') ?: '2000-01');
        $toOpt = $this->option('exchange-rates-to');

        if ($prompt)
        {
            $fromOpt = trim((string) $this->ask('From month (YYYY-MM)', $fromOpt));
            $toInput = trim((string) $this->ask('To month (YYYY-MM, empty = current)', ''));
            $toOpt = $toInput !== '' ? $toInput : null;
        }

        $args = [
            '--from' => $fromOpt,
            '--skip-existing' => true,
            '--sleep' => 2,
            '--sleep-on-error' => 15,
        ];

        if ($toOpt !== null && $toOpt !== '')
        {
            $args['--to'] = (string) $toOpt;
        }

        $this->info('Backfilling USD/ARS from BCRA...');
        $this->call('exchange-rates:backfill-bcra', $args);

        $this->info('Backfilling USD/EUR from Frankfurter...');
        $this->call('exchange-rates:backfill-frankfurter', $args);
    }
}
