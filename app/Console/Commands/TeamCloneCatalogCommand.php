<?php

namespace App\Console\Commands;

use App\Services\TeamCatalogCloneService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class TeamCloneCatalogCommand extends Command
{
    protected $signature = 'team:clone-catalog
        {source_team_id : Team ID to copy from}
        {target_team_id : Team ID to copy into}
        {--dry-run : Count rows only, no database writes}';

    protected $description = 'Clone products-module stores, categories, and products from one team to another';

    public function handle(TeamCatalogCloneService $cloneService): int
    {
        $sourceId = (int) $this->argument('source_team_id');
        $targetId = (int) $this->argument('target_team_id');
        $dryRun = (bool) $this->option('dry-run');

        try
        {
            $result = $cloneService->cloneCatalog($sourceId, $targetId, $dryRun);
        } catch (InvalidArgumentException $e)
        {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e)
        {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($dryRun)
        {
            $this->info('Dry run — no changes written.');
        } else
        {
            $this->info('Catalog cloned successfully.');
        }

        $this->table(
            ['Resource', 'Count'],
            [
                ['Stores', $result['stores']],
                ['Categories', $result['categories']],
                ['Products', $result['products']],
            ],
        );

        return self::SUCCESS;
    }
}
