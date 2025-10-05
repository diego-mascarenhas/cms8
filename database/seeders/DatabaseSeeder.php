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
		]);

		// ============================================
		// PHASE 2: Base Data (Required for modules to work)
		// ============================================
		$this->command->info('');
		$this->command->info('📊 Phase 2: Base System Data');
		$this->call([
			CurrencySeeder::class,  // Monedas (EUR, USD, etc)
			CountrySeeder::class,  // Países
			LanguageSeeder::class,  // Idiomas base
			PaymentTypeSeeder::class,  // Tipos de pago
			InvoiceTypeSeeder::class,  // Tipos de factura
			EnterpriseTaxStatusTypeSeeder::class,  // Estados fiscales
			EnterpriseTypeSeeder::class,  // Tipos de empresa
			EnterpriseStatusSeeder::class,  // Estados de empresa
			EnterpriseDepartmentSeeder::class,  // Departamentos
			ContactStatusSeeder::class,  // Estados de contacto
			ContactSentimentSeeder::class,  // Sentimientos
			ContactValorationSeeder::class,  // Valoraciones
			ProjectStatusSeeder::class,  // Estados de proyecto
			UnitsSeeder::class,  // Unidades (palabras, minutos, etc)
			FareTypesSeeder::class,  // Tipos de tarifa
			CategorySeeder::class,  // Categorías base
		]);

		// ============================================
		// PHASE 3: Demo Data (Optional - for testing/demos)
		// Uncomment to create complete demo ecosystem with:
		// - Demo Team with users
		// - Clients, Projects, Services
		// - Language variants, Collaborators
		// - Invoices, Payments, Products
		// - Templates, Messages, Task boards
		// ============================================
		// $this->command->info('');
		// $this->command->info('🎭 Phase 3: Demo Data & Ecosystem');
		// $this->call([
		//     TeamDemoSeeder::class,
		// ]);

		// ============================================
		// PHASE 3: Countries & Languages (Optional - can be added later)
		// ============================================
		// $this->command->info('');
		// $this->command->info('🌍 Phase 3: Countries & Languages');
		// $this->call([
		//     CountrySeeder::class,
		//     LanguageSeeder::class,
		// ]);

		// Clear activity log entries generated during seeding
		$this->clearAllActivities();

		$this->command->info('');
		$this->command->info('✅ HUMANO Core System installed successfully!');
		$this->command->info('');
		$this->command->info('📝 Next steps:');
		$this->command->info('   1. Run specific module seeders as needed');
		$this->command->info('   2. Install packages via: php artisan module:install <module-key>');
		$this->command->info('   3. Create your team and enable modules from the UI');
	}
}
