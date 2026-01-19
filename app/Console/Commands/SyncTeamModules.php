<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Team;
use Illuminate\Console\Command;

class SyncTeamModules extends Command
{
	protected $signature = 'team:sync-modules {team_id?}';

	protected $description = 'Sync team modules with default configuration';

	public function handle()
	{
		$teamId = $this->argument('team_id');

		if ($teamId) {
			$teams = Team::where('id', $teamId)->get();
			if ($teams->isEmpty()) {
				$this->error("Team with ID {$teamId} not found");

				return 1;
			}
		} else {
			$teams = Team::all();
		}

		// Get modules configuration from centralized config file
		$modulesConfig = config('team-modules.defaults', []);

		$allModules = Module::all();

		foreach ($teams as $team) {
			$this->info("Syncing modules for team: {$team->name} (ID: {$team->id})");

			foreach ($allModules as $module) {
				$shouldEnable = $modulesConfig[$module->key] ?? false;
				$isEnabled = $team->modules->contains($module->id);

				if ($shouldEnable && !$isEnabled) {
					$team->enableModule($module->key);
					$this->info("  ✓ {$module->name} enabled");
				} elseif (!$shouldEnable && $isEnabled) {
					$team->disableModule($module->key);
					$this->warn("  ⊗ {$module->name} disabled");
				} else {
					$this->line("  - {$module->name} unchanged");
				}
			}

			$this->info('');
		}

		$this->info('Module synchronization completed!');

		return 0;
	}
}
