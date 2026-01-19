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

		// Modules configuration - true = enabled by default, false = disabled by default
		$modulesConfig = [
			// Core modules
			'dashboard' => true,
			'users' => true,
			'settings' => true,
			'contacts' => true,
			'clients' => true,
			'list60' => true,
			'services' => false,
			'projects' => false,
			'tasks' => true,
			'notifications' => true,
			'templates' => false,
			// Additional modules (billing)
			'invoices' => true,
			'payments' => true,
			'incomes' => false,
			'expenses' => false,
			'financial' => true,
			'accounting' => false,
			// Additional modules (ecommerce)
			'products' => true,
			'orders' => true,
			'stores' => false,
			// Additional modules (infrastructure)
			'servers' => false,
			'hosting' => false,
			// Additional modules (general)
			'notes' => false,
			'collaborators' => true,
			'communications' => false,
			'enterprises' => true,
			'events' => false,
			'today' => false,
			'times' => true,
			'attendances' => false,
			'documentation' => false,
			'departments' => false,
			// Additional modules (campaigns)
			'mailer' => true,
			// Additional modules (automation)
			'funnel' => false,
			'integrations' => true,
			// Additional modules (content)
			'multimedia' => false,
			'academy' => false,
			'landings' => false,
			// Additional modules (support)
			'tickets' => true,
			'mailbox' => false,
			'chat' => true,
			// Additional modules (learning)
			'languages' => false,
			'language-variants' => false,
			'fares' => false,
			'softwares' => false,
			'certifications' => false,
			'stylebooks' => false,
		];

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
