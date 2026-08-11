<?php

namespace App\Console\Commands;

use App\Support\DatabaseSequence;
use Illuminate\Console\Command;

class SyncTableIdSequenceCommand extends Command
{
    protected $signature = 'db:sync-id-sequence
                            {table=categories : Table whose id sequence should be realigned}
                            {--column=id : ID column name}';

    protected $description = 'Realign MySQL AUTO_INCREMENT / PostgreSQL serial sequence after explicit ID inserts';

    public function handle(): int
    {
        $table = (string) $this->argument('table');
        $column = (string) $this->option('column');

        try
        {
            $next = DatabaseSequence::sync($table, $column);
        } catch (\Throwable $e)
        {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($next === null)
        {
            $this->warn("No sequence sync performed for {$table}.{$column} (empty table or unsupported driver).");

            return self::SUCCESS;
        }

        $this->info("Synced {$table}.{$column}; next insert id will be {$next}.");

        return self::SUCCESS;
    }
}
