<?php

namespace Database\Seeders;

use App\Traits\ClearsActivityLog;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
	use ClearsActivityLog;

	/**
	 * Seed the application's database.
	 *
	 * HUMANO INSTALLER - Core System Setup
	 * This seeder installs ONLY the essential components needed to run the system.
	 * Additional features and demo data should be installed via packages or optional seeders.
	 */
	public function run(): void
	{
		$this->command->info('🚀 HUMANO Installer - Starting Core System Setup...');

		// Publish billing migrations if not already published
		$this->publishBillingMigrations();

		// ============================================
		// PHASE 1: System Foundation
		// ============================================
		$this->command->info('');
		$this->command->info('📦 Phase 1: System Foundation');
		$this->call([
			RolesAndPermissionsSeeder::class,  // Roles básicos (admin, user, developer)
			ModuleSeeder::class,  // Módulos disponibles del sistema
			UserSeeder::class,  // Usuario admin inicial
			TaskStatusSeeder::class,  // Estados de tareas (TO_DO, IN_PROGRESS, etc)
			CoreModulesPermissionsSeeder::class,  // Permisos para módulos core
		]);

		// ============================================
		// PHASE 2: Base Data (Required for modules to work)
		// ============================================
		$this->command->info('');
		$this->command->info('📊 Phase 2: Base System Data');
		$this->call([
			CurrencySeeder::class,  // Currencies (EUR, USD, etc)
			CountrySeeder::class,  // Countries
			LanguageSeeder::class,  // Base languages
			PaymentTypeSeeder::class,  // Payment types
			InvoiceTypeSeeder::class,  // Invoice types
			EnterpriseTaxStatusTypeSeeder::class,  // Tax status types
			EnterpriseTypeSeeder::class,  // Enterprise types
			EnterpriseStatusSeeder::class,  // Enterprise statuses
			EnterpriseDepartmentSeeder::class,  // Departments
			ContactStatusSeeder::class,  // Contact statuses
			ContactSentimentSeeder::class,  // Sentiments
			ContactValorationSeeder::class,  // Valuations
			List60StatusesSeeder::class,  // List60 statuses
			ProjectStatusSeeder::class,  // Project statuses
			UnitsSeeder::class,  // Units (words, minutes, etc)
			FareTypesSeeder::class,  // Fare types
			CategorySeeder::class,  // Base categories
		]);

		// ============================================
		// PHASE 3: Team Data (Optional - for production/testing)
		// Uncomment the seeder you want to run:
		// - TeamDemoSeeder: Creates demo team with sample data
		// - TeamRevisionAlphaSeeder: Imports Revision Alpha team and data from remote DB
		// ============================================
		$this->command->info('');
		$this->command->info('🎭 Phase 3: Team Data & Ecosystem');
		$this->call([
			TeamRevisionAlphaSeeder::class,  // Production data import
			// TeamDemoSeeder::class,  // Demo data (alternative)
		]);

		// Clear activity log entries generated during seeding
		$this->clearAllActivities();

		$this->command->info('');
		$this->command->info('✅ HUMANO System installed successfully!');
		$this->command->info('');
		$this->command->info('📝 Next steps:');
		$this->command->info('   1. Import data: php artisan import:interactive --auto');
		$this->command->info('   2. Access at: ' . config('app.url'));
	}

	/**
	 * Publish billing migrations if needed
	 */
	private function publishBillingMigrations(): void
	{
		// Check if billing tables exist
		if (!\Illuminate\Support\Facades\Schema::hasTable('invoices') || !\Illuminate\Support\Facades\Schema::hasTable('payments')) {
			$this->command->info('📦 Publishing billing package migrations...');
			$this->command->call('vendor:publish', [
				'--tag' => 'humano-billing-migrations',
				'--force' => true,
			]);
			$this->command->info('✅ Billing migrations published');

			$this->command->info('🔧 Running billing migrations...');
			$this->command->call('migrate');
			$this->command->info('✅ Billing migrations completed');
		}
	}
}
