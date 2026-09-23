<?php

namespace App\Console\Commands;

use App\Services\Tickets\RevcmsTicketImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportRevcmsTickets extends Command
{
    protected $signature = 'tickets:import-revcms
                            {--team=2 : Team that receives every historical ticket}
                            {--connection=mysql_legacy : Source database connection}
                            {--files= : Directory of legacy attachment files}
                            {--dry-run : Count rows without writing}';

    protected $description = 'Import historical revcms tickets into one team, matching people by email';

    public function handle(RevcmsTicketImporter $importer): int
    {
        $connection = (string) $this->option('connection');

        try
        {
            $total = DB::connection($connection)->table('tickets')->count();
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $stats = $importer->import(
                $connection,
                (int) $this->option('team'),
                $this->option('files') ? (string) $this->option('files') : null,
                (bool) $this->option('dry-run'),
                function () use ($bar): void
                {
                    $bar->advance();
                },
            );
            $bar->finish();
            $this->newLine(2);
        } catch (RuntimeException $exception)
        {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Count'], collect($stats)->map(fn (int $count, string $metric) => [$metric, $count])->values()->all());

        return self::SUCCESS;
    }
}
