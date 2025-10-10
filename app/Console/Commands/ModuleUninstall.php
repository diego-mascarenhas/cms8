<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Team;
use Illuminate\Console\Command;

class ModuleUninstall extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'module:uninstall
							{module : The module key to uninstall}
							{--team= : Uninstall for specific team ID (optional, default: all teams)}
							{--force : Skip confirmation}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Uninstall/disable a module for teams (does not delete data)';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$moduleKey = $this->argument('module');
		$teamId = $this->option('team');
		$force = $this->option('force');

		$this->warn("⚠️  Uninstalling module: {$moduleKey}");
		$this->newLine();

		// Step 1: Verify module exists
		$module = Module::where('key', $moduleKey)->first();

		if (!$module) {
			$this->error("❌ Module '{$moduleKey}' not found in database.");

			return 1;
		}

		// Step 2: Confirm uninstallation
		if (!$force) {
			$message = $teamId
				? "This will disable '{$module->name}' for team ID {$teamId}."
				: "This will disable '{$module->name}' for ALL teams.";

			$this->warn($message);
			$this->warn('Note: This does NOT delete any data from the database.');
			$this->newLine();

			if (!$this->confirm('Do you want to continue?')) {
				$this->info('Uninstallation cancelled.');

				return 0;
			}
		}

		// Step 3: Disable module for teams
		$this->info('🔌 Disabling module...');
		$this->disableModuleForTeams($module, $teamId);

		$this->newLine();
		$this->info("✅ Module '{$module->name}' has been disabled successfully!");
		$this->line('💡 To re-enable it, run: php artisan module:install ' . $moduleKey);

		return 0;
	}

	/**
	 * Disable module for teams
	 */
	protected function disableModuleForTeams(Module $module, $teamId = null)
	{
		if ($teamId) {
			// Disable for specific team
			$team = Team::find($teamId);

			if (!$team) {
				$this->error("   ❌ Team with ID {$teamId} not found");

				return;
			}

			$team->disableModule($module->key);
			$this->info("   ✓ Disabled for team: {$team->name} (ID: {$teamId})");
		} else {
			// Disable for all teams
			$teams = Team::all();

			foreach ($teams as $team) {
				if (!$team->hasModule($module->key)) {
					$this->line("   ⏭️  Not installed for: {$team->name}");

					continue;
				}

				$team->disableModule($module->key);
				$this->info("   ✓ Disabled for team: {$team->name} (ID: {$team->id})");
			}
		}
	}
}
