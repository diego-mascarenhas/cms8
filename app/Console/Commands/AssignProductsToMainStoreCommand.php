<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Console\Command;

class AssignProductsToMainStoreCommand extends Command
{
    protected $signature = 'products:assign-main-store
                            {--team= : Only process this team ID}';

    protected $description = 'Assign products with no store to each team\'s main branch (sucursal principal)';

    public function handle(): int
    {
        $teamQuery = Team::query()->orderBy('id');

        $teamOption = $this->option('team');
        if ($teamOption !== null && $teamOption !== '')
        {
            $teamQuery->where('id', (int) $teamOption);
        }

        $teams = $teamQuery->get();

        if ($teams->isEmpty())
        {
            $this->warn('No teams matched.');

            return Command::FAILURE;
        }

        $totalUpdated = 0;

        foreach ($teams as $team)
        {
            $pending = Product::withoutGlobalScope('team')
                ->where('team_id', $team->id)
                ->whereNull('store_id')
                ->count();

            if ($pending === 0)
            {
                continue;
            }

            $mainStore = Store::withoutGlobalScope('team')
                ->where('team_id', $team->id)
                ->where('is_main', true)
                ->first();

            if (! $mainStore)
            {
                $mainStore = Store::ensureMainStoreForTeam((int) $team->id);
            }

            $updated = Product::withoutGlobalScope('team')
                ->where('team_id', $team->id)
                ->whereNull('store_id')
                ->update(['store_id' => $mainStore->id]);

            $totalUpdated += $updated;

            $this->info("Team {$team->id} ({$team->name}): {$updated} product(s) → main store #{$mainStore->id} ({$mainStore->name})");
        }

        if ($totalUpdated === 0)
        {
            $this->info('No products without store_id.');

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info("Done. Updated {$totalUpdated} product(s) in total.");

        return Command::SUCCESS;
    }
}
