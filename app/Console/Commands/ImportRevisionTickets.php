<?php

namespace App\Console\Commands;

use App\Services\Tickets\RevisionTicketImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportRevisionTickets extends Command
{
    protected $signature = 'tickets:import-revision
                            {--team=2 : Team that receives the tickets}
                            {--connection=mysql_legacy : Source database connection}
                            {--files= : Local directory with legacy public media folders}
                            {--dry-run : Count rows without writing}';

    protected $description = 'Import Revision Alpha tickets into this team, matching people by email';

    public function handle(RevisionTicketImporter $importer): int
    {
        try
        {
            $stats = $importer->import(
                (string) $this->option('connection'),
                (int) $this->option('team'),
                $this->option('files') ? (string) $this->option('files') : null,
                (bool) $this->option('dry-run'),
            );
        } catch (RuntimeException $exception)
        {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Count'], collect($stats)->map(fn (int $count, string $metric) => [$metric, $count])->values()->all());

        return self::SUCCESS;
    }
}
