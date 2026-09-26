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
