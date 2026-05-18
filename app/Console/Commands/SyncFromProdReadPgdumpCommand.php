<?php

namespace App\Console\Commands;

use App\Console\Concerns\ReconcilesStripeInvoicesAfterDatabaseSync;
use App\Support\DatabaseSync\PostgresCliBinaryResolver;
use App\Support\DatabaseSync\PostgresProdReadTableAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

class SyncFromProdReadPgdumpCommand extends Command
{
    use ReconcilesStripeInvoicesAfterDatabaseSync;

    private ?string $pgDumpPath = null;

    private ?string $pgRestorePath = null;

    private ?string $psqlPath = null;

    protected $signature = 'db:sync-from-prod-read-pgdump
                            {--keep-dump : Do not delete the dump file after restore}
                            {--dump= : Reuse an existing custom-format dump file (skip pg_dump)}
                            {--dry-run : Show commands without executing}
                            {--force : Skip confirmation prompt}
                            {--no-reconcile-stripe-invoices : Skip Stripe invoice status/balance mapping after restore}';

    protected $description = 'Fast exact copy: production PostgreSQL (prod_read) → local PostgreSQL via pg_dump/pg_restore';

    public function handle(): int
    {
        if (! config('app.prod_read_credentials_configured'))
        {
            $this->error('prod_read is not configured. Set DB_PROD_READ_* in .env.');

            return self::FAILURE;
        }

        if (! app()->environment('local'))
        {
            $this->error('This command only runs in the local environment.');

            return self::FAILURE;
        }

        $localConnection = (string) config('database.default');
        if (config("database.connections.{$localConnection}.driver") !== 'pgsql')
        {
            $this->error("Local connection [{$localConnection}] is not pgsql.");
            $this->line('Set DB_CONNECTION=pgsql in .env and create a local database (see .env.example).');
            $this->line('Slow alternative for MySQL: php artisan db:sync-from-prod-read --chunk=10000 --force');

            return self::FAILURE;
        }

        if (! $this->resolvePostgresBinaries())
        {
            return self::FAILURE;
        }

        $prod = config('database.connections.prod_read');
        $local = config("database.connections.{$localConnection}");
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $existingDump = trim((string) ($this->option('dump') ?? ''));

        $this->info('Source: prod_read → Target: '.$localConnection.' (pgsql)');
        $this->line("  prod: {$prod['database']}@{$prod['host']}:{$prod['port']}");
        $this->line("  local: {$local['database']}@{$local['host']}:{$local['port']}");

        if (! $dryRun && ! $force)
        {
            if (! $this->confirm('This REPLACES all objects in the local PostgreSQL database. Continue?', false))
            {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        if ($dryRun)
        {
            $this->warn('DRY RUN — commands only.');
        } else
        {
            $this->ensureLocalDatabaseExists($local);
        }

        $dumpPath = $existingDump !== ''
            ? $existingDump
            : $this->defaultDumpPath();

        if ($existingDump === '' || ! File::isFile($dumpPath))
        {
            $dumpResult = $this->runPgDump($prod, $dumpPath, $dryRun);
            if ($dumpResult !== self::SUCCESS)
            {
                return $dumpResult;
            }
        } else
        {
            $this->info("Using existing dump: {$dumpPath}");
        }

        $restoreResult = $this->runPgRestore($local, $dumpPath, $dryRun);
        if ($restoreResult !== self::SUCCESS)
        {
            return $restoreResult;
        }

        if (! $dryRun && ! $this->option('keep-dump') && $existingDump === '')
        {
            File::delete($dumpPath);
            $this->line("Removed dump: {$dumpPath}");
        } elseif (! $dryRun)
        {
            $this->info("Dump kept at: {$dumpPath}");
        }

        if (! $dryRun)
        {
            try
            {
                DB::connection($localConnection)->getPdo();
                $tableCount = (int) DB::connection($localConnection)
                    ->selectOne("SELECT count(*) AS c FROM pg_tables WHERE schemaname = 'public'")
                    ->c;
                $this->info("Local database ready ({$tableCount} tables in public schema).");
            } catch (Throwable $e)
            {
                $this->warn('Restore finished but local connection check failed: '.$e->getMessage());
            }
        }

        $this->info($dryRun ? 'Dry run completed.' : 'PostgreSQL dump/restore completed.');

        $reconcileResult = $this->reconcileStripeInvoicesAfterDatabaseSync($dryRun);
        if ($reconcileResult !== self::SUCCESS)
        {
            return $reconcileResult;
        }

        return self::SUCCESS;
    }

    private function defaultDumpPath(): string
    {
        return sys_get_temp_dir().'/humano-prod-read-'.date('Ymd-His').'.dump';
    }

    private function resolvePostgresBinaries(): bool
    {
        $this->pgDumpPath = PostgresCliBinaryResolver::resolve('pg_dump');
        $this->pgRestorePath = PostgresCliBinaryResolver::resolve('pg_restore');
        $this->psqlPath = PostgresCliBinaryResolver::resolve('psql');

        if ($this->pgDumpPath !== null && $this->pgRestorePath !== null)
        {
            $this->line("Using: {$this->pgDumpPath}");

            return true;
        }

        $this->error('pg_dump and pg_restore were not found.');
        $this->newLine();
        $this->line('Install client tools (server alone is not enough):');
        $this->line('  brew install libpq');
        $this->line('  echo \'export PATH="/opt/homebrew/opt/libpq/bin:$PATH"\' >> ~/.zshrc');
        $this->newLine();
        $this->line('Or set the bin directory in .env:');
        $this->line('  PG_BIN=/opt/homebrew/opt/libpq/bin');
        $this->line('  # Postgres.app example:');
        $this->line('  PG_BIN=/Applications/Postgres.app/Contents/Versions/latest/bin');
        $this->newLine();
        $this->line('Searched:');
        foreach (PostgresCliBinaryResolver::searchedPathsFor('pg_dump') as $path)
        {
            $this->line('  '.$path);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function ensureLocalDatabaseExists(array $config): void
    {
        $database = (string) $config['database'];
        $exists = Process::env($this->pgEnv((string) ($config['password'] ?? '')))
            ->run([
                $this->psqlPath ?? 'psql',
                '-h', (string) $config['host'],
                '-p', (string) $config['port'],
                '-U', (string) $config['username'],
                '-d', 'postgres',
                '-tAc', "SELECT 1 FROM pg_database WHERE datname = '".str_replace("'", "''", $database)."'",
            ]);

        if ($exists->successful() && trim($exists->output()) === '1')
        {
            return;
        }

        $this->info("Creating local database [{$database}]…");
        $create = Process::env($this->pgEnv((string) ($config['password'] ?? '')))
            ->run([
                $this->psqlPath ?? 'psql',
                '-h', (string) $config['host'],
                '-p', (string) $config['port'],
                '-U', (string) $config['username'],
                '-d', 'postgres',
                '-c', 'CREATE DATABASE "'.str_replace('"', '""', $database).'"',
            ]);

        if (! $create->successful())
        {
            $this->warn('Could not create database automatically: '.$create->errorOutput());
            $this->line('Create it manually, then re-run this command.');
        }
    }

    /**
     * @param  array<string, mixed>  $prod
     */
    private function runPgDump(array $prod, string $dumpPath, bool $dryRun): int
    {
        $schema = $this->prodSchemaName();
        $excludedTables = PostgresProdReadTableAccess::excludedTablesForDump();

        if ($excludedTables !== [])
        {
            $this->warn('Excluding '.count($excludedTables).' prod tables (no SELECT for user read):');
            $this->line('  '.implode(', ', $excludedTables));
            $this->line('For a full dump, ask DBA: GRANT SELECT ON ALL TABLES IN SCHEMA public TO read;');
        }

        $command = [
            $this->pgDumpPath ?? 'pg_dump',
            '-h', (string) $prod['host'],
            '-p', (string) $prod['port'],
            '-U', (string) $prod['username'],
            '-d', (string) $prod['database'],
            '--no-owner',
            '--no-acl',
            '-Fc',
            '-f', $dumpPath,
        ];

        foreach ($excludedTables as $table)
        {
            $command[] = '--exclude-table='.$schema.'.'.$table;
        }

        $this->line('pg_dump → '.$dumpPath);

        if ($dryRun)
        {
            return self::SUCCESS;
        }

        $this->info('Dumping production (this may take a few minutes)…');
        $result = Process::timeout(3600)
            ->env($this->pgEnv((string) ($prod['password'] ?? ''), (string) ($prod['sslmode'] ?? 'prefer')))
            ->run($command);

        if (! $result->successful())
        {
            $this->error('pg_dump failed: '.$result->errorOutput());

            return self::FAILURE;
        }

        $sizeMb = round(File::size($dumpPath) / 1024 / 1024, 1);
        $this->info("Dump complete ({$sizeMb} MB).");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $local
     */
    private function runPgRestore(array $local, string $dumpPath, bool $dryRun): int
    {
        $command = [
            $this->pgRestorePath ?? 'pg_restore',
            '-h', (string) $local['host'],
            '-p', (string) $local['port'],
            '-U', (string) $local['username'],
            '-d', (string) $local['database'],
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-acl',
            $dumpPath,
        ];

        $this->line('pg_restore → '.$local['database']);

        if ($dryRun)
        {
            return self::SUCCESS;
        }

        $this->info('Restoring into local PostgreSQL…');
        $result = Process::timeout(3600)
            ->env($this->pgEnv((string) ($local['password'] ?? '')))
            ->run($command);

        if (! $result->successful())
        {
            $stderr = $result->errorOutput();
            if (str_contains($stderr, 'errors ignored on restore'))
            {
                $this->warn('pg_restore reported warnings (often safe):');
                $this->line($stderr);
            } else
            {
                $this->error('pg_restore failed: '.$stderr);

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function prodSchemaName(): string
    {
        $schema = config('database.connections.prod_read.search_path', 'public');

        return is_array($schema) ? (string) ($schema[0] ?? 'public') : (string) $schema;
    }

    /**
     * @return array<string, string>
     */
    private function pgEnv(string $password, ?string $sslmode = null): array
    {
        $env = ['PGPASSWORD' => $password];
        if ($sslmode !== null && $sslmode !== '')
        {
            $env['PGSSLMODE'] = $sslmode;
        }

        return $env;
    }
}
