<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\DemoDataService;
use Illuminate\Console\Command;

class StartCommand extends Command
{
    protected $signature = 'start
                            {--fresh : Fresh install (migrate:fresh + seed), then optionally demo (non-interactive: use with --demo)}
                            {--demo : After fresh, or alone: load demo data (non-interactive)}
                            {--stores : Import Pedimos Facil stores into teams (non-interactive)}';

    protected $description = 'Humano setup: fresh install (migrate:fresh --seed) then choose to load demo data';

    private const OPT_FRESH = 'Fresh install (migrate:fresh + seed)';

    private const OPT_DEMO_ONLY = 'Load demo data only (requires DB already seeded)';

    private const OPT_CHAT = 'Chat (conversar con el asistente en terminal; podés indicar teléfono de cliente)';

    private const OPT_STORES = 'Import stores (Pedimos Facil -> Teams)';

    private const OPT_EXIT = 'Exit';

    public function handle(): int
    {
        $fresh = $this->option('fresh');
        $demo = $this->option('demo');
        $stores = $this->option('stores');

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
            ->where('name', "REVISION ALPHA's Team")
            ->first();

        if (! $team)
        {
            $this->warn("Revision Alpha team not found. Run 'Run database seed' first (or: php artisan db:seed).");

            return self::FAILURE;
        }

        DemoDataService::createClientsAndProjects($team->id, $this);

        return self::SUCCESS;
    }

    private function runStoresImport(): void
    {
        $this->info('Importing Pedimos Facil stores into teams...');
        $this->call('import:interactive', ['--stores' => true]);
    }
}
