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
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseDepartmentSeeder::class,
            EnterpriseSeeder::class,
            ContactStatusSeeder::class,
            ContactSentimentSeeder::class,
            ContactValorationSeeder::class,
            ContactSeeder::class,
            ContactSourceSeeder::class,
            ContactSkillsSeeder::class,
            EnterpriseOrganizationSeeder::class,
            List60StatusesSeeder::class,
            List60Seeder::class,
            ProjectStatusSeeder::class,
            TaskStatusSeeder::class,
            TaskSeeder::class,
            ModuleSeeder::class,
            ModuleCategorySeeder::class,
            CategorySeeder::class,
            UnitsSeeder::class,
            FareTypesSeeder::class,
            FaresSeeder::class,
            FareUnitSeeder::class,
            SoftwareTypesSeeder::class,
            SoftwareSeeder::class,
            TopicsSeeder::class,
            CertificationsSeeder::class,
            StylebooksSeeder::class,
        ]);
    }
}
