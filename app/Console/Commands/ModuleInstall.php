<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ModuleInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:install
							{module : The module key to install (e.g., billing, ecommerce, tickets)}
							{--team= : Install for specific team ID (optional, default: all teams)}
							{--skip-migrations : Skip running migrations}
							{--skip-seeders : Skip running seeders}
							{--force : Force install even if already installed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a module and run its migrations, seeders, and publish assets automatically';

    /**
     * Module package name mappings
     */
    protected $modulePackages = [
        'billing' => 'humano-billing',
        'ecommerce' => 'humano-ecommerce',
        'tickets' => 'humano-tickets',
        'academy' => 'humano-academy',
        'mailbox' => 'humano-mailbox',
        'chat' => 'humano-chat',
        'infrastructure' => 'humano-infrastructure',
        'access-control' => 'humano-access-control',
        'mailer' => 'humano-mailer',
        'version-control' => 'humano-version-control',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $moduleKey = $this->argument('module');
        $teamId = $this->option('team');
        $skipMigrations = $this->option('skip-migrations');
        $skipSeeders = $this->option('skip-seeders');
        $force = $this->option('force');

        $this->info("🚀 Installing module: {$moduleKey}");
        $this->newLine();

        // Step 1: Verify module exists
        $module = Module::where('key', $moduleKey)->first();

        if (! $module)
        {
            $this->error("❌ Module '{$moduleKey}' not found in database.");
            $this->warn('💡 Available modules:');
            $availableModules = Module::all(['key', 'name', 'is_core']);
            foreach ($availableModules as $mod)
            {
                $type = $mod->is_core ? '[CORE]' : '[ADDON]';
                $this->line("   {$type} {$mod->key} - {$mod->name}");
            }

            return 1;
        }

        $this->info("✅ Module found: {$module->name}");

        // Step 2: Check if module has a package
        $packageName = $this->modulePackages[$moduleKey] ?? null;

        if ($packageName)
        {
            $this->info("📦 Package: {$packageName}");
        }

        // Step 3: Run migrations if not skipped
        if (! $skipMigrations)
        {
            $this->newLine();
            $this->info('📊 Running migrations...');
            $this->runModuleMigrations($packageName, $moduleKey);
        } else
        {
            $this->warn('⏭️  Skipped migrations');
        }

        // Step 4: Publish assets if package exists
        if ($packageName)
        {
            $this->newLine();
            $this->info('📂 Publishing package assets...');
            $this->publishPackageAssets($packageName);
        }

        // Step 5: Enable module for teams
        $this->newLine();
        $this->info('🔌 Enabling module for teams...');
        $this->enableModuleForTeams($module, $teamId, $force);

        // Step 6: Run seeders if not skipped
        if (! $skipSeeders)
        {
            $this->newLine();
            $this->info('🌱 Running seeders...');
            $this->runModuleSeeders($packageName, $moduleKey);
        } else
        {
            $this->warn('⏭️  Skipped seeders');
        }

        // Step 7: Show installation summary
        $this->newLine();
        $this->showInstallationSummary($module, $packageName, $teamId);

        $this->newLine();
        $this->info("✅ Module '{$module->name}' installed successfully!");

        return 0;
    }

    /**
     * Run migrations for the module
     */
    protected function runModuleMigrations($packageName, $moduleKey)
    {
        try
        {
            // Check for pending migrations
            $pendingMigrations = $this->getPendingMigrations($moduleKey);

            if (empty($pendingMigrations))
            {
                $this->info('   ✓ No pending migrations');

                return;
            }

            $this->info('   Found '.count($pendingMigrations).' pending migration(s)');

            // Run migrations
            Artisan::call('migrate', ['--force' => true], $this->output);

            $this->info('   ✓ Migrations completed');
        } catch (\Exception $e)
        {
            $this->error('   ❌ Migration error: '.$e->getMessage());
        }
    }

    /**
     * Get pending migrations for a module
     */
    protected function getPendingMigrations($moduleKey)
    {
        $ran = DB::table('migrations')->pluck('migration')->toArray();
        $allMigrations = $this->getMigrationFiles();

        // Filter migrations that contain the module key
        $moduleMigrations = array_filter($allMigrations, function ($migration) use ($moduleKey)
        {
            return stripos($migration, $moduleKey) !== false;
        });

        // Get pending migrations
        return array_diff($moduleMigrations, $ran);
    }

    /**
     * Get all migration files
     */
    protected function getMigrationFiles()
    {
        $migrations = [];

        // Get migrations from main project
        $projectMigrations = glob(database_path('migrations/*.php'));
        foreach ($projectMigrations as $file)
        {
            $migrations[] = basename($file, '.php');
        }

        // Get migrations from packages
        $vendorPath = base_path('vendor/idoneo/*/database/migrations');
        $packageMigrations = glob($vendorPath.'/*.php');
        foreach ($packageMigrations as $file)
        {
            $migrations[] = basename($file, '.php');
        }

        return $migrations;
    }

    /**
     * Publish package assets (views, config, migrations)
     */
    protected function publishPackageAssets($packageName)
    {
        try
        {
            // Publish migrations
            $result = Artisan::call('vendor:publish', [
                '--tag' => "{$packageName}-migrations",
                '--force' => true,
            ], $this->output);

            if ($result === 0)
            {
                $this->info("   ✓ Published {$packageName} migrations");
            }

            // Publish config
            $result = Artisan::call('vendor:publish', [
                '--tag' => "{$packageName}-config",
                '--force' => true,
            ], $this->output);

            if ($result === 0)
            {
                $this->info("   ✓ Published {$packageName} config");
            }

            // Publish views
            $result = Artisan::call('vendor:publish', [
                '--tag' => "{$packageName}-views",
                '--force' => true,
            ], $this->output);

            if ($result === 0)
            {
                $this->info("   ✓ Published {$packageName} views");
            }
        } catch (\Exception $e)
        {
            $this->warn('   ⚠️  No publishable assets found or already published');
        }
    }

    /**
     * Enable module for teams
     */
    protected function enableModuleForTeams(Module $module, $teamId = null, $force = false)
    {
        if ($teamId)
        {
            // Enable for specific team
            $team = Team::find($teamId);

            if (! $team)
            {
                $this->error("   ❌ Team with ID {$teamId} not found");

                return;
            }

            $alreadyEnabled = $team->hasModule($module->key);

            if ($alreadyEnabled && ! $force)
            {
                $this->warn("   ⚠️  Module already enabled for team: {$team->name}");

                return;
            }

            $team->enableModule($module->key);
            $this->info("   ✓ Enabled for team: {$team->name} (ID: {$teamId})");
        } else
        {
            // Enable for all teams
            $teams = Team::all();

            foreach ($teams as $team)
            {
                $alreadyEnabled = $team->hasModule($module->key);

                if ($alreadyEnabled && ! $force)
                {
                    $this->line("   ⏭️  Already enabled for: {$team->name}");

                    continue;
                }

                $team->enableModule($module->key);
                $this->info("   ✓ Enabled for team: {$team->name} (ID: {$team->id})");
            }
        }
    }

    /**
     * Run module seeders
     */
    protected function runModuleSeeders($packageName, $moduleKey)
    {
        // Map of known seeders
        $seederMap = [
            'billing' => [
                'Database\Seeders\PaymentTypeSeeder',
                'Database\Seeders\InvoiceTypeSeeder',
            ],
            'access-control' => [
                'Database\Seeders\PermissionSeeder',
            ],
        ];

        if (isset($seederMap[$moduleKey]))
        {
            foreach ($seederMap[$moduleKey] as $seederClass)
            {
                try
                {
                    Artisan::call('db:seed', ['--class' => $seederClass], $this->output);
                    $this->info("   ✓ Seeder executed: {$seederClass}");
                } catch (\Exception $e)
                {
                    $this->warn("   ⚠️  Seeder not found or already executed: {$seederClass}");
                }
            }
        } else
        {
            $this->info('   ✓ No seeders configured for this module');
        }
    }

    /**
     * Show installation summary
     */
    protected function showInstallationSummary(Module $module, $packageName, $teamId)
    {
        $this->info('═══════════════════════════════════════════════');
        $this->info('📋 INSTALLATION SUMMARY');
        $this->info('═══════════════════════════════════════════════');
        $this->line("Module Name:    {$module->name}");
        $this->line("Module Key:     {$module->key}");
        $this->line('Package:        '.($packageName ?? 'N/A (core module)'));
        $this->line('Type:           '.($module->is_core ? 'Core Module' : 'Add-on Module'));
        $this->line('Status:         '.($module->status ? 'Active' : 'Inactive'));

        if ($teamId)
        {
            $team = Team::find($teamId);
            $this->line("Installed for:  Team '{$team->name}' (ID: {$teamId})");
        } else
        {
            $teamCount = Team::count();
            $this->line("Installed for:  All teams ({$teamCount} teams)");
        }

        // Show related tables
        $this->newLine();
        $this->info('📊 Database Tables:');
        $tables = $this->getModuleTables($module->key);
        if (! empty($tables))
        {
            foreach ($tables as $table)
            {
                $recordCount = DB::table($table)->count();
                $this->line("   • {$table} ({$recordCount} records)");
            }
        } else
        {
            $this->line('   • No specific tables detected');
        }
    }

    /**
     * Get tables related to a module
     */
    protected function getModuleTables($moduleKey)
    {
        $tableMap = [
            'billing' => ['invoices', 'invoice_items', 'invoice_types', 'payments', 'payment_types', 'payment_accounts'],
            'ecommerce' => ['orders', 'order_items', 'products', 'product_categories'],
            'tickets' => ['tickets', 'ticket_messages', 'ticket_categories'],
            'academy' => ['courses', 'lessons', 'enrollments'],
            'mailbox' => ['mailbox_messages', 'mailbox_folders'],
            'chat' => ['chat_messages', 'chat_rooms'],
            'infrastructure' => ['servers', 'domains', 'hosting_accounts'],
        ];

        $tables = $tableMap[$moduleKey] ?? [];

        // Filter only existing tables
        return array_filter($tables, function ($table)
        {
            return DB::getSchemaBuilder()->hasTable($table);
        });
    }
}
