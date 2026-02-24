<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Get command instance, create dummy if null
     */
    private function getCommand()
    {
        if ($this->command === null)
        {
            return new class
            {
                public function info($message)
                {
                    echo "[INFO] $message\n";
                }

                public function warn($message)
                {
                    echo "[WARN] $message\n";
                }

                public function error($message)
                {
                    echo "[ERROR] $message\n";
                }

                public function call($command, $args = [])
                {
                    return 0;
                }
            };
        }

        return $this->command;
    }

    /**
     * Seed the application's database.
     *
     * HUMANO INSTALLER - Core System Setup
     * This seeder installs ONLY the essential components needed to run the system.
     * Additional features and demo data should be installed via packages or optional seeders.
     */
    public function run(): void
    {
        $this->getCommand()->info('🚀 HUMANO Installer - Starting Core System Setup...');

        // Publish billing migrations if not already published
        $this->publishBillingMigrations();

        // ============================================
        // PHASE 1: System Foundation
        // ============================================
        $this->getCommand()->info('');
        $this->getCommand()->info('📦 Phase 1: System Foundation');
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
        $this->getCommand()->info('');
        $this->getCommand()->info('📊 Phase 2: Base System Data');
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
            HostingProjectAndTaskCategoriesSeeder::class,  // Project & task categories (hosting)
            WebDevProjectAndTaskCategoriesSeeder::class,  // Project & task categories (web dev & infrastructure)
            PromptSeeder::class,  // Prompts (AI instructions by module_id)
            SubscriptionProductSeeder::class,  // Subscription products (Mailer, Mentoring, Hosting)
        ]);

        // ============================================
        // PHASE 3: Team Data (Optional - for production/testing)
        // Uncomment the seeder you want to run:
        // - TeamDemoSeeder: Creates demo team with sample data
        // - TeamRevisionAlphaSeeder: Imports Revision Alpha team and data from remote DB
        // - TeamHumanoSeeder: Creates Humano team and users
        // ============================================
        $this->getCommand()->info('');
        $this->getCommand()->info('🎭 Phase 3: Team Data & Ecosystem');
        $this->call([
            TeamRevisionAlphaSeeder::class,  // Production data import
            // TeamDemoSeeder::class,  // Demo data (alternative)
            TeamHumanoSeeder::class,  // Humano team setup
        ]);

        // Activity log was removed from the application

        $this->getCommand()->info('');
        $this->getCommand()->info('✅ HUMANO System installed successfully!');
        $this->getCommand()->info('');
        $this->getCommand()->info('📝 Next steps:');
        $this->getCommand()->info('   1. Import data: php artisan import:interactive --auto');
        $this->getCommand()->info('   2. Access at: '.config('app.url'));
    }

    /**
     * Publish billing migrations if needed
     */
    private function publishBillingMigrations(): void
    {
        // Check if billing tables exist
        if (! \Illuminate\Support\Facades\Schema::hasTable('invoices') || ! \Illuminate\Support\Facades\Schema::hasTable('payments'))
        {
            $this->getCommand()->info('📦 Publishing billing package migrations...');
            $this->getCommand()->call('vendor:publish', [
                '--tag' => 'humano-billing-migrations',
                '--force' => true,
            ]);
            $this->getCommand()->info('✅ Billing migrations published');

            $this->getCommand()->info('🔧 Running billing migrations...');
            $this->getCommand()->call('migrate');
            $this->getCommand()->info('✅ Billing migrations completed');
        }
    }
}
