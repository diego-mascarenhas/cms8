<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Hash;

class UserSeeder extends Seeder
{
	/**
	 * Create the default admin user for HUMANO system.
	 *
	 * This seeder creates ONLY the essential admin user needed to access the system.
	 * Additional users should be created via UI or team-specific seeders.
	 */
	public function run()
	{
		$this->command->info('   👤 Creating admin user...');

		// Check if admin already exists
		$existingAdmin = User::where('email', 'admin@humano.app')->first();

		if ($existingAdmin) {
			$this->command->warn('   ⏭️  Admin user already exists, skipping...');

			return;
		}

		// Create Admin User
		$admin = User::factory()->create([
			'name' => 'Admin',
			'email' => 'admin@humano.app',
			'password' => Hash::make('Simplicity!'),
			'email_verified_at' => now(),
		]);

		// Assign admin role (role ID 2 = Admin)
		$admin->assignRole([2]);

		// Create default team
		$team = $admin->ownedTeams()->create([
			'name' => 'Demo',
			'personal_team' => false,
		]);

		// Attach user to team
		$admin->teams()->attach($team->id, [
			'role' => 'admin',
			'created_at' => now(),
		]);

		// Set as current team
		$admin->update(['current_team_id' => $team->id]);

		// Enable core modules based on configuration
		$this->enableCoreModulesForTeam($team);

		$this->command->info('   ✅ Admin user created successfully!');
		$this->command->info('      Email: admin@humano.app');
		$this->command->info('      Password: Simplicity!');
		$this->command->info('      Team: ' . $team->name);

		// Note: Additional users can be created via:
		// - Team-specific seeders (TeamDemoSeeder, TeamRevisionAlphaSeeder, etc.)
		// - UI user management
		// - Custom seeders for specific deployments
	}

	/**
	 * Enable core modules for a team based on their default configuration
	 */
	private function enableCoreModulesForTeam($team)
	{
		$this->command->info('   🔧 Enabling modules...');

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
			'notifications' => false,
			'templates' => false,
			// Additional modules (billing)
			'invoices' => true,
			'payments' => false,
			'incomes' => false,
			'expenses' => false,
			'financial' => true,
			'accounting' => false,
			// Additional modules (ecommerce)
			'products' => true,
			'orders' => false,
			'stores' => false,
			// Additional modules (infrastructure)
			'servers' => false,
			'hosting' => false,
			// Additional modules (general)
			'notes' => false,
			'collaborators' => false,
			'communications' => false,
			'enterprises' => false,
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
			'integrations' => false,
			// Additional modules (content)
			'multimedia' => false,
			'academy' => false,
			'landings' => false,
			// Additional modules (support)
			'tickets' => false,
			'mailbox' => false,
			'chat' => false,
			// Additional modules (learning)
			'languages' => false,
			'language-variants' => false,
			'fares' => false,
			'softwares' => false,
			'certifications' => false,
			'stylebooks' => false,
		];

		$allModules = \App\Models\Module::all();

		foreach ($allModules as $module) {
			$shouldEnable = $modulesConfig[$module->key] ?? false;

			if ($shouldEnable) {
				$team->enableModule($module->key);
				$this->command->info("      ✓ {$module->name} enabled");
			} else {
				$this->command->info("      ⊗ {$module->name} registered (disabled by default)");
			}
		}
	}
}
