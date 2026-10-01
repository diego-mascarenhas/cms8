<?php

namespace App\Support\DatabaseSync;

use Illuminate\Support\Facades\DB;

class PostgresProdReadTableAccess
{
    /**
     * @var array<string, array{readable: list<string>, denied: list<string>}>
     */
    private static array $cache = [];

    /**
     * @var array<string, array{denied: list<string>, owned: list<array{sequence: string, table: string, column: string}>}>
     */
    private static array $sequenceCache = [];

    /**
     * @return list<string>
     */
    public static function readableTables(string $connection): array
    {
        return self::probe($connection)['readable'];
    }

    /**
     * @return list<string>
     */
    public static function deniedTables(string $connection): array
    {
        return self::probe($connection)['denied'];
    }

    /**
     * Tables pg_dump must skip because the read user has no SELECT.
     *
     * @return list<string>
     */
    public static function excludedTablesForDump(string $connection = 'prod_read'): array
    {
        return self::deniedTables($connection);
    }

    /**
     * Sequences pg_dump must skip because the read user has no SELECT.
     * Reading pg_class does not require SELECT on the sequence itself.
     *
     * @return list<string>
     */
    public static function excludedSequencesForDump(string $connection = 'prod_read'): array
    {
        return self::probeSequences($connection)['denied'];
    }

    /**
     * Owned sequences that were skipped in the dump and must be recreated on the local database.
     *
     * @return list<array{sequence: string, table: string, column: string}>
     */
    public static function sequencesToRecreateLocally(string $connection = 'prod_read'): array
    {
        return self::probeSequences($connection)['owned'];
    }

    /**
     * @param  list<string>  $relations
     * @return list<string>
     */
    public static function excludeTableArguments(string $schema, array $relations): array
    {
        $arguments = [];

        foreach ($relations as $relation)
        {
            $arguments[] = '--exclude-table='.$schema.'.'.$relation;
        }

        return $arguments;
    }

    /**
     * @param  list<object|array<string, mixed>>  $rows
     * @return array{denied: list<string>, owned: list<array{sequence: string, table: string, column: string}>}
     */
    public static function partitionSequenceRows(array $rows): array
    {
        $denied = [];
        $owned = [];
        $seen = [];

        foreach ($rows as $row)
        {
            $name = is_array($row) ? ($row['sequence_name'] ?? null) : ($row->sequence_name ?? null);
            $canSelect = is_array($row) ? ($row['can_select'] ?? false) : ($row->can_select ?? false);
            $table = is_array($row) ? ($row['table_name'] ?? null) : ($row->table_name ?? null);
            $column = is_array($row) ? ($row['column_name'] ?? null) : ($row->column_name ?? null);

            if (! is_string($name) || $name === '' || isset($seen[$name]) || self::isGranted($canSelect))
            {
                continue;
            }

            $seen[$name] = true;
            $denied[] = $name;

            if (is_string($table) && $table !== '' && is_string($column) && $column !== '')
            {
                $owned[] = [
                    'sequence' => $name,
                    'table' => $table,
                    'column' => $column,
                ];
            }
        }

        return [
            'denied' => $denied,
            'owned' => $owned,
        ];
    }

    /**
     * @param  list<object|array<string, mixed>>  $rows
     * @return array{readable: list<string>, denied: list<string>}
     */
    public static function partitionRows(array $rows): array
    {
        $readable = [];
        $denied = [];

        foreach ($rows as $row)
        {
            $name = is_array($row) ? ($row['name'] ?? null) : ($row->name ?? null);
            $canSelect = is_array($row) ? ($row['can_select'] ?? false) : ($row->can_select ?? false);

            if (! is_string($name) || $name === '')
            {
                continue;
            }

            if (self::isGranted($canSelect))
            {
                $readable[] = $name;
            } else
            {
                $denied[] = $name;
            }
        }

        return [
            'readable' => $readable,
            'denied' => $denied,
        ];
    }

    /**
     * @return array{readable: list<string>, denied: list<string>}
     */
    private static function probe(string $connection): array
    {
        if (isset(self::$cache[$connection]))
        {
            return self::$cache[$connection];
        }

        $schema = self::schemaFor($connection);
        $rows = DB::connection($connection)->select(
            "SELECT c.relname AS name, has_table_privilege(current_user, c.oid, 'SELECT') AS can_select
             FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = ? AND c.relkind = 'r'
             ORDER BY c.relname",
            [$schema],
        );

        return self::$cache[$connection] = self::partitionRows($rows);
    }

    /**
     * @return array{denied: list<string>, owned: list<array{sequence: string, table: string, column: string}>}
     */
    private static function probeSequences(string $connection): array
    {
        if (isset(self::$sequenceCache[$connection]))
        {
            return self::$sequenceCache[$connection];
        }

        $schema = self::schemaFor($connection);
        $rows = DB::connection($connection)->select(
            "SELECT seq.relname AS sequence_name,
                    tab.relname AS table_name,
                    att.attname AS column_name,
                    has_sequence_privilege(current_user, seq.oid, 'SELECT') AS can_select
             FROM pg_class seq
             JOIN pg_namespace n ON n.oid = seq.relnamespace
             LEFT JOIN pg_depend dep ON dep.objid = seq.oid AND dep.deptype = 'a' AND dep.classid = 'pg_class'::regclass
             LEFT JOIN pg_class tab ON tab.oid = dep.refobjid AND tab.relkind = 'r'
             LEFT JOIN pg_attribute att ON att.attrelid = tab.oid AND att.attnum = dep.refobjsubid AND att.attnum > 0
             WHERE n.nspname = ? AND seq.relkind = 'S'
             ORDER BY seq.relname",
            [$schema],
        );

        return self::$sequenceCache[$connection] = self::partitionSequenceRows($rows);
    }

    private static function schemaFor(string $connection): string
    {
        $searchPath = (string) config("database.connections.{$connection}.search_path", 'public');
        $schema = trim(explode(',', $searchPath)[0]);

        return $schema !== '' ? $schema : 'public';
    }

    private static function isGranted(mixed $value): bool
    {
        if (is_bool($value))
        {
            return $value;
        }

        if (is_int($value))
        {
            return $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 't', 'true'], true);
    }
}
