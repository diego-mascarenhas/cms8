<?php

namespace App\Console\Commands;

use App\Models\ServiceSync;
use App\Services\Billing\ServiceSyncImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ImportServiceSyncsCommand extends Command
{
    protected $signature = 'service-syncs:import
                            {--team_id= : Import only one team}
                            {--provider=stripe : Provider code in service_syncs}
                            {--limit=500 : Max service_syncs rows to process}
                            {--fallback-email : Resolve enterprise by email when customer_id/code does not match}
                            {--link-code-on-email-match : When fallback by email succeeds uniquely, write customer_id into enterprises.code}
                            {--dry-run : Preview without writing}';

    protected $description = 'Map service_syncs rows into core services table (create-only idempotent by subscription_id)';

    public function handle(ServiceSyncImporter $importer): int
    {
        if (! Schema::hasTable('service_syncs'))
        {
            $this->error('Table service_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $provider = strtolower(trim((string) $this->option('provider')));
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $fallbackEmail = (bool) $this->option('fallback-email');
        $linkCodeOnEmailMatch = (bool) $this->option('link-code-on-email-match');

        $query = ServiceSync::query()
            ->where('provider', $provider)
            ->orderBy('team_id')
            ->orderByRaw('current_period_end IS NULL')
            ->orderBy('current_period_end')
            ->orderBy('id');

        if ($teamId)
        {
            $query->where('team_id', $teamId);
        }

        $query->whereNotExists(function ($q)
        {
            $q->from('services')
                ->whereColumn('services.subscription_id', 'service_syncs.id')
                ->whereNull('services.deleted_at');
        });

        $rows = $query->limit($limit)->get();

        $processed = 0;
        $created = 0;
        $skipped = 0;

        foreach ($rows as $row)
        {
            if (! $row instanceof ServiceSync)
            {
                continue;
            }

            $processed++;

            if ($dryRun)
            {
                [$enterpriseId] = $importer->resolveEnterpriseId(
                    $row,
                    $fallbackEmail,
                    $linkCodeOnEmailMatch,
                    dryRun: true,
                );

                if (! $enterpriseId)
                {
                    $skipped++;
                    $reason = $fallbackEmail ? 'customer_id/code or unique email' : 'customer_id/code';
                    $this->warn("Skip {$row->id}: enterprise not found by {$reason} for team {$row->team_id}");

                    continue;
                }

                $this->line("[dry-run] create service from sync_id={$row->id} team={$row->team_id} enterprise={$enterpriseId}");
                $created++;

                continue;
            }

            try
            {
                $importer->createServiceFromSync(
                    $row,
                    categoryId: null,
                    fallbackEmail: $fallbackEmail,
                    linkCodeOnEmailMatch: $linkCodeOnEmailMatch,
                );
                $created++;
            } catch (RuntimeException $e)
            {
                $skipped++;
                $this->warn("Skip {$row->id}: ".$e->getMessage());
            }
        }

        $this->info(
            "Processed: {$processed} | created: {$created} | skipped: {$skipped}".
            ($dryRun ? ' | dry-run' : ''),
        );

        return self::SUCCESS;
    }
}
