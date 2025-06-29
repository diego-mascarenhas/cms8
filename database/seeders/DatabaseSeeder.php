<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
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
            CategorySeeder::class,
            CertificationsSeeder::class,
            StylebooksSeeder::class,
            NotificationTypesSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
// Test comment
