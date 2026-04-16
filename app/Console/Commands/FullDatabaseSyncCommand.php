<?php

namespace App\Console\Commands;

use App\Support\DatabaseSync\TableDependencyResolver;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FullDatabaseSyncCommand extends Command
{
    /**
     * @var array<string, true>
     */
    private array $syncWarningsShown = [];

    protected $signature = 'db:full-sync
                            {--source=mysql_legacy : Source connection name}
                            {--target=pgsql : Target connection name}
                            {--chunk=1000 : Rows per batch insert}
                            {--only= : Comma-separated tables to sync}
                            {--exclude=migrations,failed_jobs,password_reset_tokens,personal_access_tokens : Comma-separated tables to skip}
                            {--no-truncate : Do not truncate target tables before sync}
                            {--no-sequence-reset : Do not reset PostgreSQL sequences after sync}
                            {--dry-run : Show sync plan without writing changes}';

    protected $description = 'Full database sync from MySQL (source) to PostgreSQL (target); uses upsert so it is safe if target already has rows (e.g. after seeders)';

    public function handle(): int
    {
        $sourceName = (string) $this->option('source');
        $targetName = (string) $this->option('target');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $onlyTables = $this->parseListOption((string) $this->option('only'));
        $excludedTables = $this->parseListOption((string) $this->option('exclude'));
        $isDryRun = (bool) $this->option('dry-run');

        $source = DB::connection($sourceName);
        $target = DB::connection($targetName);

        $this->info("Source connection: {$sourceName} ({$source->getDriverName()})");
        $this->info("Target connection: {$targetName} ({$target->getDriverName()})");
        if ($isDryRun)
        {
            $this->warn('DRY RUN enabled: no data will be written to target.');
        }

        if ($source->getDriverName() !== 'mysql')
        {
            $this->error('This command expects a MySQL source connection.');

            return self::FAILURE;
        }

        if ($target->getDriverName() !== 'pgsql')
        {
            $this->error('This command expects a PostgreSQL target connection.');

            return self::FAILURE;
        }

        $sourceTables = $this->getMysqlTables($source);
        $targetTables = $this->getPostgresTables($target);
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
            $this->warn('No tables to sync after filters.');

            return self::SUCCESS;
        }

        $dependencies = $this->getPostgresForeignKeyDependencies($target, $tables);
        $orderedTables = TableDependencyResolver::resolve($dependencies, $tables);

        $this->line('Tables to sync: '.count($orderedTables));

        if (! $this->option('no-truncate') && ! $isDryRun)
        {
            $this->truncateTargetTables($target, $orderedTables);
        } elseif (! $this->option('no-truncate') && $isDryRun)
        {
            $this->line('DRY RUN: target truncation skipped.');
        }

        foreach ($orderedTables as $table)
        {
            $this->syncTable($sourceName, $targetName, $table, $chunkSize, $isDryRun);
        }

        if (! $this->option('no-sequence-reset') && ! $isDryRun)
        {
            $this->resetPostgresSequences($target, $targetName, $orderedTables);
        } elseif (! $this->option('no-sequence-reset') && $isDryRun)
        {
            $this->line('DRY RUN: sequence reset skipped.');
        }

        $this->info($isDryRun ? 'Dry run completed successfully.' : 'Full sync completed successfully.');

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
    private function getMysqlTables(ConnectionInterface $connection): array
    {
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
     * @return array<int, string>
     */
    private function getPostgresTables(ConnectionInterface $connection): array
    {
        $rows = $connection
            ->table('information_schema.tables')
            ->selectRaw('table_name as name')
            ->where('table_schema', 'public')
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
    private function getPostgresForeignKeyDependencies(ConnectionInterface $target, array $tables): array
    {
        $lookup = array_fill_keys($tables, true);
        $dependencies = [];

        foreach ($tables as $table)
        {
            $dependencies[$table] = [];
        }

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

    /**
     * @param  array<int, string>  $tables
     */
    private function truncateTargetTables(ConnectionInterface $target, array $tables): void
    {
        $quoted = array_map(fn ($table) => $this->quoteIdentifier($table), $tables);
        $target->statement('TRUNCATE TABLE '.implode(', ', $quoted).' RESTART IDENTITY CASCADE');
        $this->info('Target tables truncated with RESTART IDENTITY CASCADE.');
    }

    private function syncTable(string $sourceName, string $targetName, string $table, int $chunkSize, bool $isDryRun): void
    {
        $sourceColumns = Schema::connection($sourceName)->getColumnListing($table);
        $targetColumns = Schema::connection($targetName)->getColumnListing($table);
        $columns = array_values(array_intersect($sourceColumns, $targetColumns));

        if (empty($columns))
        {
            $this->warn("Skipping {$table}: no shared columns.");

            return;
        }

        $orderColumn = $this->resolveChunkOrderColumn($targetName, $table, $columns, $sourceName);
        $conflictColumns = $this->resolvePostgresPrimaryKeyColumns($targetName, $table, $columns);
        $totalRows = (int) DB::connection($sourceName)->table($table)->count();
        $mode = $isDryRun ? 'PLAN' : 'SYNC';
        $pkHint = $conflictColumns === [] ? 'no PG PK in shared cols' : implode(', ', $conflictColumns);
        $this->line("[{$mode}] {$table}: {$totalRows} rows, ".count($columns)." shared columns, ordered by {$orderColumn}, upsert on ({$pkHint})");

        if ($isDryRun)
        {
            return;
        }

        $targetConnection = DB::connection($targetName);
        $updateColumns = array_values(array_diff($columns, $conflictColumns));

        $flushBatch = function (iterable $rows) use ($targetConnection, $table, $conflictColumns, $updateColumns): void
        {
            $payload = [];
            foreach ($rows as $row)
            {
                $payload[] = (array) $row;
            }

            if ($payload === [])
            {
                return;
            }

            if ($conflictColumns === [])
            {
                $this->warnOnce(
                    'insert_without_pg_pk',
                    'Some tables use INSERT because the PostgreSQL primary key is missing from shared columns; use --no-truncate with care or align schemas.',
                );
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

        $sourceQuery = DB::connection($sourceName)->table($table)->select($columns);

        $maxBatches = max(1, (int) ceil($totalRows / $chunkSize));

        if (count($conflictColumns) === 1)
        {
            $cursorColumn = $conflictColumns[0];
            $chunkIndex = 0;
            $sourceQuery->chunkById(
                $chunkSize,
                function ($rows) use ($flushBatch, $table, $totalRows, $chunkSize, $maxBatches, &$chunkIndex): void
                {
                    $chunkIndex++;
                    if ($totalRows > 2000)
                    {
                        $this->line("  … {$table}: cursor {$chunkIndex}/{$maxBatches} (".number_format($totalRows).' rows, chunk size '.$chunkSize.') — writing…');
                    }
                    $flushBatch($rows);
                },
                $cursorColumn,
            );
        } else
        {
            $chunkIndex = 0;
            $sourceQuery->orderBy($orderColumn)->chunk(
                $chunkSize,
                function ($rows) use ($flushBatch, $table, $totalRows, $maxBatches, &$chunkIndex): void
                {
                    $chunkIndex++;
                    if ($totalRows > 2000)
                    {
                        $this->line("  … {$table}: offset {$chunkIndex}/{$maxBatches} (".number_format($totalRows).' rows) — writing…');
                    }
                    $flushBatch($rows);
                },
            );
        }
    }

    /**
     * PostgreSQL ON CONFLICT must target columns covered by a PRIMARY KEY or UNIQUE constraint.
     *
     * @param  array<int, string>  $sharedColumns
     * @return array<int, string>
     */
    private function resolvePostgresPrimaryKeyColumns(string $targetConnection, string $table, array $sharedColumns): array
    {
        $lookup = array_fill_keys($sharedColumns, true);
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
            ['public', $table, 'PRIMARY KEY'],
        );

        $fullPrimaryKey = [];
        foreach ($rows as $row)
        {
            $fullPrimaryKey[] = (string) $row->column_name;
        }

        if ($fullPrimaryKey === [])
        {
            return [];
        }

        foreach ($fullPrimaryKey as $name)
        {
            if (! isset($lookup[$name]))
            {
                return [];
            }
        }

        return $fullPrimaryKey;
    }

    /**
     * Stable column for chunk ordering (MySQL source).
     *
     * @param  array<int, string>  $sharedColumns
     */
    private function resolveChunkOrderColumn(string $targetConnection, string $table, array $sharedColumns, string $sourceConnection): string
    {
        $pkColumns = $this->resolvePostgresPrimaryKeyColumns($targetConnection, $table, $sharedColumns);
        if ($pkColumns !== [] && isset($pkColumns[0]))
        {
            return $pkColumns[0];
        }

        return $this->resolveMysqlPrimaryKeyFirstColumn($sourceConnection, $table, $sharedColumns);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function resolveMysqlPrimaryKeyFirstColumn(string $sourceConnection, string $table, array $columns): string
    {
        $database = DB::connection($sourceConnection)->getDatabaseName();

        $primaryKey = DB::connection($sourceConnection)
            ->table('information_schema.columns')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('column_key', 'PRI')
            ->orderBy('ordinal_position')
            ->value('column_name');

        if (is_string($primaryKey) && in_array($primaryKey, $columns, true))
        {
            return $primaryKey;
        }

        return $columns[0];
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function resetPostgresSequences(ConnectionInterface $target, string $targetName, array $tables): void
    {
        foreach ($tables as $table)
        {
            $columns = Schema::connection($targetName)->getColumnListing($table);

            foreach ($columns as $column)
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

        $this->info('PostgreSQL sequences resynchronized.');
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
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
}
