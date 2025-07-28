<?php

namespace Database\Seeders;

use App\Traits\ClearsActivityLog;
use Illuminate\Database\Seeder;
use Spatie\Activitylog\Models\Activity;

class DatabaseSeeder extends Seeder
{
	use ClearsActivityLog;

	/**
	 * Seed the application's database.
	 */
	public function run(): void
	{
		$this->call([
			CurrencySeeder::class,
			MessageTypeSeeder::class,
			TemplateSeeder::class,
			MessageSeeder::class,
			PageSeeder::class,
			RolesAndPermissionsSeeder::class,
			PolicySeeder::class,
			CountrySeeder::class,
			LanguageSeeder::class,
			SourceSeeder::class,
			UserSeeder::class,
			PaymentTypeSeeder::class,
			InvoiceTypeSeeder::class,
			ProjectStatusSeeder::class,
			EnterpriseTypeSeeder::class,
			EnterpriseStatusSeeder::class,
			EnterpriseDepartmentSeeder::class,
			ContactStatusSeeder::class,
			ContactSentimentSeeder::class,
			ContactValorationSeeder::class,
			SoftwareSeeder::class,
			TopicsSeeder::class,
			FareTypesSeeder::class,
			FaresSeeder::class,
			UnitsSeeder::class,
			FareUnitSeeder::class,
			ContactSkillsSeeder::class,
			List60StatusesSeeder::class,

			// Client-specific seeders (must run first to create teams)
			//EnterpriseSeeder::class,
			//ContactSeeder::class,
			//TeamRevisionAlphaSeeder::class, // Revision Alpha client data for Team 2
			//TeamHumanoSeeder::class,        // Humano client data for Team 3
			//TeamBboSeeder::class,           // BBO client data for Team 4
			LanguageVariantSeeder::class, // Language variants for Team 1
			CollaboratorsSeeder::class, // Demo collaborators for Team 1
			TeamDemoSeeder::class, // Demo data for Team 1 (clients, projects, fares, software, certifications, experience)

			// Module and category seeders (run after teams are created)
			ModuleSeeder::class,
			CategorySeeder::class,
			ModuleCategorySeeder::class,
			CertificationsSeeder::class,
			StylebooksSeeder::class,
			NotificationTypesSeeder::class,
			NotificationSeeder::class,
		]);

		// Clear activity log entries generated during seeding
		$this->clearAllActivities();
	}
}
// Test comment
