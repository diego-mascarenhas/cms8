<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Keep auto-increment / serial sequences aligned after explicit ID inserts.
 */
class DatabaseSequence
{
    /**
     * Sync the next ID for a table's primary key with MAX(column).
     *
     * @return int|null Next ID that will be used (when known), or null when unsupported/empty.
     */
    public static function sync(string $table, string $column = 'id'): ?int
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $table) || ! preg_match('/^[a-zA-Z0-9_]+$/', $column))
        {
            throw new InvalidArgumentException('Invalid table or column name.');
        }

        $driver = DB::getDriverName();

        if (! in_array($driver, ['pgsql', 'mysql'], true))
        {
            return null;
        }

        $maxValue = (int) (DB::table($table)->max($column) ?? 0);

        if ($driver === 'pgsql')
        {
            if ($maxValue < 1)
            {
                return null;
            }

            // Identifiers are validated above; quote them so Postgres does not treat
            // the table name as a column reference (pg_get_serial_sequence needs text).
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', '{$column}'), ?, true)",
                [$maxValue],
            );

            return $maxValue + 1;
        }

        $next = max($maxValue + 1, 1);
        $escapedTable = str_replace('`', '``', $table);
        DB::statement("ALTER TABLE `{$escapedTable}` AUTO_INCREMENT = {$next}");

        return $next;
    }
}
