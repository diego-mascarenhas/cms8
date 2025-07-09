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
            LanguageVariantSeeder::class,
            SourceSeeder::class,
            UserSeeder::class,
            PaymentTypeSeeder::class,
            InvoiceTypeSeeder::class,
            ProjectStatusSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseDepartmentSeeder::class,
            EnterpriseSeeder::class,
            ContactStatusSeeder::class,
            ContactSentimentSeeder::class,
            ContactValorationSeeder::class,
            ContactSeeder::class,
            SoftwareSeeder::class,
            TopicsSeeder::class,
            FareTypesSeeder::class,
            FaresSeeder::class,
            UnitsSeeder::class,
            FareUnitSeeder::class,
            ContactSkillsSeeder::class,
            
            // Client-specific seeders (must run first to create teams)
            RevisionAlphaSeeder::class, // Revision Alpha client data for Team 2
            HumanoSeeder::class,        // Humano client data for Team 3
            BboSeeder::class,           // BBO client data for Team 4
            CollaboratorsSeeder::class, // Demo collaborators for Team 1
            
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
