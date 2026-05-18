<?php

namespace App\Console\Commands;

use App\Console\Concerns\ReconcilesStripeInvoicesAfterDatabaseSync;
use App\Support\DatabaseSync\PostgresProdReadTableAccess;
use App\Support\DatabaseSync\TableDependencyResolver;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncFromProdReadCommand extends Command
{
    use ReconcilesStripeInvoicesAfterDatabaseSync;

    private const string SOURCE_CONNECTION = 'prod_read';

    /**
     * @var array<string, true>
     */
    private array $syncWarningsShown = [];

    private bool $targetWasTruncated = false;

    protected $signature = 'db:sync-from-prod-read
                            {--target= : Target connection name (default: database.default)}
                            {--chunk=2000 : Rows per batch (lower uses less memory on large tables)}
                            {--only= : Comma-separated tables to sync}
                            {--exclude=migrations : Comma-separated tables to skip}
                            {--no-truncate : Do not truncate target tables before sync}
                            {--upsert : Force upsert even after truncate (slower; default is bulk INSERT)}
                            {--no-sequence-reset : Do not reset PostgreSQL sequences after sync (pgsql target only)}
                            {--no-auto-increment-reset : Do not reset MySQL AUTO_INCREMENT after sync (mysql target only)}
                            {--migrate-first : Run php artisan migrate on the local database before syncing data}
                            {--dry-run : Show sync plan without writing changes}
                            {--force : Skip confirmation prompt}
                            {--no-reconcile-stripe-invoices : Skip Stripe invoice status/balance mapping after sync}';

    protected $description = 'Sync prod_read to local via Laravel (SELECT only; no prod permission changes). Supports local MySQL or PostgreSQL';

    public function handle(): int
    {
        if (! config('app.prod_read_credentials_configured'))
        {
            $this->error('prod_read is not configured. Set DB_PROD_READ_* in .env (see .env.example).');

            return self::FAILURE;
        }

        if (! app()->environment('local'))
        {
            $this->error('This command only runs in the local environment.');

            return self::FAILURE;
        }

        $memoryLimit = (string) env('DB_SYNC_MEMORY_LIMIT', '2048M');
        ini_set('memory_limit', $memoryLimit);

        try
        {
            DB::connection(self::SOURCE_CONNECTION)->getPdo();
        } catch (Throwable $e)
        {
            $this->error('Cannot connect to prod_read: '.$e->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('migrate-first') && ! (bool) $this->option('dry-run'))
        {
            $this->info('Running local migrations first…');
            $this->call('migrate', ['--force' => true]);
        }

        $targetName = (string) ($this->option('target') ?: config('database.default'));
        $chunkSize = max(1, (int) $this->option('chunk'));
        $onlyTables = $this->parseListOption((string) $this->option('only'));
        $excludedTables = $this->parseListOption((string) $this->option('exclude'));
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $source = DB::connection(self::SOURCE_CONNECTION);
        $target = DB::connection($targetName);

        if ($source->getDriverName() !== 'pgsql')
        {
            $this->error('prod_read must be a PostgreSQL connection.');

            return self::FAILURE;
        }

        $targetDriver = $target->getDriverName();
        if (! in_array($targetDriver, ['mysql', 'pgsql'], true))
        {
            $this->error('Target must be mysql or pgsql.');

            return self::FAILURE;
        }

        $this->info('Source: '.self::SOURCE_CONNECTION.' (pgsql) → Target: '.$targetName.' ('.$targetDriver.')');
        $this->comment('Uses SELECT only on prod — no GRANT changes needed. Tables without read access are skipped automatically.');
        if ($targetDriver === 'pgsql')
        {
            $this->comment('Faster full copy (excludes unreadable tables): php artisan db:sync-from-prod-read-pgdump --force');
        }
        if ($isDryRun)
        {
            $this->warn('DRY RUN: no data will be written.');
        }

        if (! $isDryRun && ! $force && ! $this->option('no-truncate'))
        {
            if (! $this->confirm('This will TRUNCATE and replace all synced tables on the local database. Continue?', false))
            {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $sourceTables = PostgresProdReadTableAccess::readableTables(self::SOURCE_CONNECTION);
        $denied = PostgresProdReadTableAccess::deniedTables(self::SOURCE_CONNECTION);
        if ($denied !== [])
        {
            $this->warn('Skipping '.count($denied).' prod tables (read user has no SELECT — normal for Laravel sync):');
            $this->line('  '.implode(', ', $denied));
        }
        $targetTables = $this->getTargetTables($target, $targetName, $targetDriver);
        $tables = array_values(array_intersect($sourceTables, $targetTables));

        if (! empty($onlyTables))
        {
            $tables = array_values(array_intersect($tables, $onlyTables));
        }

        if (! empty($excludedTables))
        {
            $tables = array_values(array_diff($tables, $excludedTables));
        }

        if (empty($tables))
        {
            $this->reportEmptySyncPlan($sourceTables, $targetTables, $excludedTables, $targetName, $targetDriver);

            return self::FAILURE;
        }

        $dependencies = $this->getForeignKeyDependencies($target, $tables, $targetDriver);
        $orderedTables = TableDependencyResolver::resolve($dependencies, $tables);

        $this->line('Tables to sync: '.count($orderedTables));

        if (! $this->option('no-truncate') && ! $isDryRun)
        {
            $this->truncateTargetTables($target, $orderedTables, $targetDriver);
            $this->targetWasTruncated = true;
        } elseif (! $this->option('no-truncate') && $isDryRun)
        {
            $this->line('DRY RUN: target truncation skipped.');
        }

        $insertOnly = $this->targetWasTruncated && ! (bool) $this->option('upsert');
        if ($insertOnly)
        {
            $this->info('Mode: bulk INSERT (tables truncated). Much faster than upsert.');
        }

        foreach ($orderedTables as $table)
        {
            $this->syncTable(self::SOURCE_CONNECTION, $targetName, $table, $chunkSize, $isDryRun, $insertOnly, $targetDriver);
        }

        if ($targetDriver === 'pgsql' && ! $this->option('no-sequence-reset') && ! $isDryRun)
        {
            $this->resetPostgresSequences($target, $targetName, $orderedTables);
        }

        if ($targetDriver === 'mysql' && ! $this->option('no-auto-increment-reset') && ! $isDryRun)
        {
            $this->resetMysqlAutoIncrements($target, $targetName, $orderedTables);
        }

        $this->info($isDryRun ? 'Dry run completed.' : 'Sync from prod_read completed.');

        $reconcileResult = $this->reconcileStripeInvoicesAfterDatabaseSync($isDryRun);
        if ($reconcileResult !== self::SUCCESS)
        {
            return $reconcileResult;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function parseListOption(string $value): array
    {
        if (trim($value) === '')
        {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * @return array<int, string>
     */
    private function getTargetTables(ConnectionInterface $connection, string $connectionName, string $driver): array
    {
        if ($driver === 'pgsql')
        {
            $schema = config("database.connections.{$connectionName}.search_path", 'public');
            $schemaName = is_array($schema) ? (string) ($schema[0] ?? 'public') : (string) $schema;

            $rows = $connection->select(
                'SELECT tablename AS name FROM pg_tables WHERE schemaname = ? ORDER BY tablename',
                [$schemaName],
            );

            return array_map(static fn ($row) => (string) $row->name, $rows);
        }

        $database = $connection->getDatabaseName();
        $rows = $connection
            ->table('information_schema.tables')
            ->selectRaw('table_name as name')
            ->where('table_schema', $database)
            ->where('table_type', 'BASE TABLE')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return array_map(static fn ($name) => (string) $name, $rows);
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<string, array<int, string>>
     */
    private function getForeignKeyDependencies(ConnectionInterface $target, array $tables, string $driver): array
    {
        $lookup = array_fill_keys($tables, true);
        $dependencies = [];

        foreach ($tables as $table)
        {
            $dependencies[$table] = [];
        }

        if ($driver === 'pgsql')
        {
            $rows = $target->select(
                'SELECT tc.table_name AS child_table, ccu.table_name AS parent_table
                 FROM information_schema.table_constraints tc
                 JOIN information_schema.constraint_column_usage ccu
                   ON tc.constraint_name = ccu.constraint_name
                  AND tc.constraint_schema = ccu.constraint_schema
                 WHERE tc.constraint_type = ?
                   AND tc.table_schema = ?',
                ['FOREIGN KEY', 'public'],
            );

            foreach ($rows as $row)
            {
                $child = (string) $row->child_table;
                $parent = (string) $row->parent_table;

                if (! isset($lookup[$child]) || ! isset($lookup[$parent]))
                {
                    continue;
                }

                $dependencies[$child][] = $parent;
            }

            return $dependencies;
        }

        $database = $target->getDatabaseName();
        $rows = $target->select(
            'SELECT TABLE_NAME AS child_table, REFERENCED_TABLE_NAME AS parent_table
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database],
        );

        foreach ($rows as $row)
        {
            $child = (string) $row->child_table;
            $parent = (string) $row->parent_table;

            if (! isset($lookup[$child]) || ! isset($lookup[$parent]))
            {
                continue;
            }

            $dependencies[$child][] = $parent;
        }

        return $dependencies;
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function truncateTargetTables(ConnectionInterface $target, array $tables, string $driver): void
    {
        if ($driver === 'pgsql')
        {
            $quoted = array_map(fn ($table) => $this->quotePgIdentifier($table), $tables);
            $target->statement('TRUNCATE TABLE '.implode(', ', $quoted).' RESTART IDENTITY CASCADE');
            $this->info('Target tables truncated with RESTART IDENTITY CASCADE ('.count($tables).' tables).');

            return;
        }

        $target->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table)
        {
            $target->statement('TRUNCATE TABLE `'.str_replace('`', '``', $table).'`');
        }

        $target->statement('SET FOREIGN_KEY_CHECKS=1');
        $this->info('Target tables truncated ('.count($tables).' tables).');
    }

    private function syncTable(
        string $sourceName,
        string $targetName,
        string $table,
        int $chunkSize,
        bool $isDryRun,
        bool $insertOnly,
        string $targetDriver,
    ): void {
        $sourceColumns = Schema::connection($sourceName)->getColumnListing($table);
        $targetColumns = Schema::connection($targetName)->getColumnListing($table);
        $columns = array_values(array_intersect($sourceColumns, $targetColumns));

        if (empty($columns))
        {
            $this->warn("Skipping {$table}: no shared columns.");

            return;
        }

        $orderColumn = $this->resolveChunkOrderColumn($targetName, $table, $columns, $targetDriver);
        $conflictColumns = $this->resolvePrimaryKeyColumns($targetName, $table, $columns, $targetDriver);
        $totalRows = (int) DB::connection($sourceName)->table($table)->count();
        $mode = $isDryRun ? 'PLAN' : 'SYNC';
        $writeMode = $insertOnly ? 'insert' : 'upsert';
        $pkHint = $conflictColumns === [] ? 'no PK in shared cols' : implode(', ', $conflictColumns);
        $this->line("[{$mode}] {$table}: {$totalRows} rows, ".count($columns)." cols, {$writeMode} ({$pkHint})");

        if ($isDryRun)
        {
            return;
        }

        if ($insertOnly)
        {
            $this->truncateSingleTargetTable(DB::connection($targetName), $table, $targetDriver);
        }

        $targetConnection = DB::connection($targetName);
        $updateColumns = array_values(array_diff($columns, $conflictColumns));

        $writePayload = function (array $payload) use ($targetConnection, $table, $conflictColumns, $updateColumns, $insertOnly): void
        {
            if ($payload === [])
            {
                return;
            }

            if ($insertOnly || $conflictColumns === [])
            {
                if ($conflictColumns === [])
                {
                    $this->warnOnce(
                        'insert_without_pk',
                        'Some tables use INSERT without a shared primary key; prefer --no-truncate only when you know the schema aligns.',
                    );
                }
                $targetConnection->table($table)->insert($payload);

                return;
            }

            if ($updateColumns === [])
            {
                $targetConnection->table($table)->insertOrIgnore($payload);

                return;
            }

            $targetConnection->table($table)->upsert($payload, $conflictColumns, $updateColumns);
        };

        $maxBatches = max(1, (int) ceil($totalRows / $chunkSize));
        $batch = [];
        $batchIndex = 0;

        $sourceQuery = DB::connection($sourceName)
            ->table($table)
            ->select($columns)
            ->orderBy($orderColumn);

        foreach ($sourceQuery->cursor() as $row)
        {
            $batch[] = $this->normalizeRowForTarget((array) $row, $columns, $targetDriver);

            if (count($batch) < $chunkSize)
            {
                continue;
            }

            $batchIndex++;
            if ($totalRows > 2000)
            {
                $this->line("  … {$table}: batch {$batchIndex}/{$maxBatches}");
            }
            $writePayload($batch);
            $batch = [];
        }

        if ($batch !== [])
        {
            $batchIndex++;
            if ($totalRows > 2000 && $batchIndex <= $maxBatches)
            {
                $this->line("  … {$table}: batch {$batchIndex}/{$maxBatches}");
            }
            $writePayload($batch);
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function normalizeRowForTarget(array $record, array $columns, string $targetDriver): array
    {
        foreach ($record as $key => $value)
        {
            if (! in_array($key, $columns, true))
            {
                unset($record[$key]);

                continue;
            }

            if ($targetDriver === 'mysql')
            {
                if (is_bool($value))
                {
                    $record[$key] = $value ? 1 : 0;
                } elseif (is_array($value) || is_object($value))
                {
                    $record[$key] = json_encode($value);
                }
            }
        }

        return $record;
    }

    /**
     * @param  array<int, string>  $sharedColumns
     * @return array<int, string>
     */
    /**
     * @param  array<int, string>  $sharedColumns
     * @return array<int, string>
     */
    private function resolvePrimaryKeyColumns(string $targetConnection, string $table, array $sharedColumns, string $driver): array
    {
        $lookup = array_fill_keys($sharedColumns, true);
        $schema = $driver === 'pgsql' ? 'public' : DB::connection($targetConnection)->getDatabaseName();

        $rows = DB::connection($targetConnection)->select(
            'SELECT kcu.column_name
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_schema = kcu.constraint_schema
              AND tc.constraint_name = kcu.constraint_name
             WHERE tc.table_schema = ?
               AND tc.table_name = ?
               AND tc.constraint_type = ?
             ORDER BY kcu.ordinal_position',
            [$schema, $table, 'PRIMARY KEY'],
        );

        $primaryKey = [];
        foreach ($rows as $row)
        {
            $primaryKey[] = (string) $row->column_name;
        }

        if ($primaryKey === [])
        {
            return [];
        }

        foreach ($primaryKey as $name)
        {
            if (! isset($lookup[$name]))
            {
                return [];
            }
        }

        return $primaryKey;
    }

    /**
     * @param  array<int, string>  $sharedColumns
     */
    private function resolveChunkOrderColumn(string $targetConnection, string $table, array $sharedColumns, string $driver): string
    {
        $pkColumns = $this->resolvePrimaryKeyColumns($targetConnection, $table, $sharedColumns, $driver);
        if ($pkColumns !== [] && isset($pkColumns[0]))
        {
            return $pkColumns[0];
        }

        return $sharedColumns[0];
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function resetPostgresSequences(ConnectionInterface $target, string $targetName, array $tables): void
    {
        foreach ($tables as $table)
        {
            foreach (Schema::connection($targetName)->getColumnListing($table) as $column)
            {
                $sequenceRow = $target->selectOne(
                    'SELECT pg_get_serial_sequence(?, ?) AS sequence_name',
                    ["public.{$table}", $column],
                );

                $sequence = $sequenceRow?->sequence_name;
                if (! is_string($sequence) || $sequence === '')
                {
                    continue;
                }

                $maxValue = (int) $target->table($table)->max($column);
                $target->statement('SELECT setval(?, ?, true)', [$sequence, max($maxValue, 1)]);
            }
        }

        $this->info('PostgreSQL sequences reset.');
    }

    private function quotePgIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function truncateSingleTargetTable(ConnectionInterface $target, string $table, string $driver): void
    {
        if ($driver === 'pgsql')
        {
            $target->statement('TRUNCATE TABLE '.$this->quotePgIdentifier($table).' RESTART IDENTITY CASCADE');

            return;
        }

        $target->statement('SET FOREIGN_KEY_CHECKS=0');
        $target->statement('TRUNCATE TABLE `'.str_replace('`', '``', $table).'`');
        $target->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function resetMysqlAutoIncrements(ConnectionInterface $target, string $targetName, array $tables): void
    {
        foreach ($tables as $table)
        {
            $columns = Schema::connection($targetName)->getColumnListing($table);

            foreach ($columns as $column)
            {
                $extra = $target->selectOne(
                    'SELECT EXTRA FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    [$target->getDatabaseName(), $table, $column],
                );

                if (! $extra || ! str_contains(strtolower((string) $extra->EXTRA), 'auto_increment'))
                {
                    continue;
                }

                $maxValue = (int) $target->table($table)->max($column);
                $next = max($maxValue + 1, 1);
                $target->statement(
                    'ALTER TABLE `'.str_replace('`', '``', $table).'` AUTO_INCREMENT = '.$next,
                );
            }
        }

        $this->info('MySQL AUTO_INCREMENT values reset.');
    }

    private function warnOnce(string $key, string $message): void
    {
        if (isset($this->syncWarningsShown[$key]))
        {
            return;
        }

        $this->syncWarningsShown[$key] = true;
        $this->warn($message);
    }

    /**
     * @param  array<int, string>  $sourceTables
     * @param  array<int, string>  $targetTables
     * @param  array<int, string>  $excludedTables
     */
    private function reportEmptySyncPlan(
        array $sourceTables,
        array $targetTables,
        array $excludedTables,
        string $targetName,
        string $targetDriver,
    ): void {
        $this->error('No tables to sync after filters.');
        $this->line('  prod readable tables: '.count($sourceTables));
        $this->line('  local tables ('.$targetName.', '.$targetDriver.'): '.count($targetTables));

        if (count($targetTables) === 0)
        {
            $this->newLine();
            $this->warn('Local database has no tables. Create the schema first, then sync data:');
            $this->line('  php artisan migrate');
            $this->line('  php artisan db:sync-from-prod-read --force --chunk=10000');
            $this->line('Or in one step:');
            $this->line('  php artisan db:sync-from-prod-read --migrate-first --force --chunk=10000');

            return;
        }

        if (count($sourceTables) === 0)
        {
            $this->warn('Cannot read any table from prod_read. Check VPN, credentials, and DB_PROD_READ_* in .env.');

            return;
        }

        $missingLocally = array_values(array_diff(array_slice($sourceTables, 0, 8), $targetTables));
        if ($missingLocally !== [])
        {
            $this->line('  sample prod tables missing locally: '.implode(', ', $missingLocally));
            $this->line('  Run: php artisan migrate --force');
        }
    }
}
