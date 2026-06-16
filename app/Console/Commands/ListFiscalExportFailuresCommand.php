<?php

namespace App\Console\Commands;

use App\Models\FiscalExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ListFiscalExportFailuresCommand extends Command
{
    protected $signature = 'fiscal:export-failures
                            {--team_id= : Limit to a single team}
                            {--platform= : Limit to a single platform}
                            {--limit=50 : Maximum rows to show}';

    protected $description = 'List fiscal export failures for review (Cuéntica, ARCA, ...)';

    public function handle(): int
    {
        if (! Schema::hasTable('fiscal_exports'))
        {
            $this->error('Table fiscal_exports does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $query = FiscalExport::query()->failed()->latest('last_attempted_at');

        if ($teamId = $this->option('team_id'))
        {
            $query->where('team_id', (int) $teamId);
        }

        if ($platform = $this->option('platform'))
        {
            $query->where('platform', $platform);
        }

        $rows = $query->limit((int) $this->option('limit'))->get();

        if ($rows->isEmpty())
        {
            $this->info('No fiscal export failures.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Invoice', 'Platform', 'Attempts', 'Last attempt', 'Error'],
            $rows->map(fn (FiscalExport $row): array => [
                $row->id,
                $row->invoice_id,
                $row->platform,
                $row->attempts,
                optional($row->last_attempted_at)->toDateTimeString(),
                Str::limit((string) $row->error_message, 80),
            ])->all(),
        );

        $this->line('Retry with: php artisan fiscal:export-invoices --retry-failed');

        return self::SUCCESS;
    }
}
