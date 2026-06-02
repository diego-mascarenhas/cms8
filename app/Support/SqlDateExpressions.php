<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Driver-aware YEAR/MONTH fragments for raw SELECT/GROUP BY (MySQL, PostgreSQL, SQLite).
 */
final class SqlDateExpressions
{
    public static function year(string $dateColumn): string
    {
        return match (DB::connection()->getDriverName())
        {
            'pgsql' => "EXTRACT(YEAR FROM {$dateColumn})::int",
            'sqlite' => "CAST(strftime('%Y', {$dateColumn}) AS INTEGER)",
            default => "YEAR({$dateColumn})",
        };
    }

    public static function month(string $dateColumn): string
    {
        return match (DB::connection()->getDriverName())
        {
            'pgsql' => "EXTRACT(MONTH FROM {$dateColumn})::int",
            'sqlite' => "CAST(strftime('%m', {$dateColumn}) AS INTEGER)",
            default => "MONTH({$dateColumn})",
        };
    }
}
