<?php

namespace App\Console\Commands;

use App\Services\ProjectCategoryLegacyImportService;
use Illuminate\Console\Command;

class SyncLegacyProjectCategoriesCommand extends Command
{
    protected $signature = 'projects:sync-legacy-categories
                            {--apply : Create missing categories preserving Legacy IDs (skipped on ID conflicts)}
                            {--remap-projects : Set projects.category_id from Legacy id_categoria when that ID exists in projects}
                            {--only-missing : In report mode, show only non-ok rows}';

    protected $description = 'Report / fix Legacy Desarrollos project categories (padre=40) vs local categories';

    public function handle(ProjectCategoryLegacyImportService $service): int
    {
        $apply = (bool) $this->option('apply');
        $remap = (bool) $this->option('remap-projects');
        $onlyMissing = (bool) $this->option('only-missing');

        try
        {
            $analysis = $service->analyze();
        } catch (\Throwable $e)
        {
            $this->error('Could not analyze Legacy project categories: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($analysis['message'])
        {
            $this->error($analysis['message']);

            return self::FAILURE;
        }

        $rows = $analysis['rows'];
        if ($onlyMissing)
        {
            $rows = array_values(array_filter($rows, fn (array $row) => $row['status'] !== 'ok'));
        }

        $this->info('Legacy project categories (categorias_generales padre = '.ProjectCategoryLegacyImportService::LEGACY_PARENT_ID.')');
        $this->newLine();

        $this->table(
            ['Legacy ID', 'Name', 'Status', 'Local ID', 'Local module', 'Detail'],
            array_map(fn (array $row) => [
                $row['legacy_id'],
                $row['legacy_name'],
                $row['status'],
                $row['local_id'] ?? '—',
                $row['local_module'] ?? '—',
                $row['detail'],
            ], $rows),
        );

        $summary = $analysis['summary'];
        $this->newLine();
        $this->info(sprintf(
            'Summary: total=%d ok=%d missing=%d id_conflict=%d name_elsewhere=%d',
            $summary['total'],
            $summary['ok'],
            $summary['missing'],
            $summary['id_conflict'],
            $summary['name_elsewhere'],
        ));

        if (! $apply && ! $remap)
        {
            $this->newLine();
            $this->comment('Report only. Suggested:');
            $this->line('  php artisan projects:sync-legacy-categories --remap-projects');

            return self::SUCCESS;
        }

        if ($apply)
        {
            $this->newLine();
            $this->warn('Applying: create missing rows with Legacy IDs (conflicts skipped)...');

            try
            {
                $result = $service->syncPreservingIds(remapProjects: false);
            } catch (\Throwable $e)
            {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->info(sprintf(
                'Created: %d | Already ok: %d | Skipped: %d',
                $result['created'],
                $result['updated'],
                $result['skipped'],
            ));

            if ($result['skipped_rows'] !== [])
            {
                $this->newLine();
                $this->warn('Skipped (need manual decision):');
                $this->table(
                    ['Legacy ID', 'Name', 'Status', 'Detail'],
                    array_map(fn (array $row) => [
                        $row['legacy_id'],
                        $row['legacy_name'],
                        $row['status'],
                        $row['detail'],
                    ], $result['skipped_rows']),
                );
            }
        }

        if ($remap)
        {
            $this->newLine();
            $this->warn('Remapping projects.category_id from Legacy id_categoria...');
            $count = $service->remapProjectCategoryIds();
            $this->info("Projects updated: {$count}");
        }

        return self::SUCCESS;
    }
}
