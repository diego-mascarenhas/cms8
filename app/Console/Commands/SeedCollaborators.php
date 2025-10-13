<?php

namespace App\Console\Commands;

use Database\Seeders\CollaboratorsSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\LanguageVariantSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedCollaborators extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:collaborators
							{--fresh : Clear existing collaborator data before seeding}
							{--sql-file= : Path to the SQL file with collaborator data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed collaborators data with language variants and combinations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting collaborators seeding process...');

        // Clear existing data if requested
        if ($this->option('fresh'))
        {
            $this->warn('⚠️  Clearing existing collaborator data...');
            $this->clearCollaboratorData();
        }

        // Seed languages and variants first
        $this->info('📝 Seeding base languages...');
        $this->call('db:seed', ['--class' => LanguageSeeder::class]);

        $this->info('🌐 Seeding language variants...');
        $this->call('db:seed', ['--class' => LanguageVariantSeeder::class]);

        // Seed collaborators
        $this->info('👥 Seeding collaborators...');
        $sqlFile = $this->option('sql-file');
        if ($sqlFile)
        {
            $this->info("📂 Using SQL file: {$sqlFile}");
            // You could add logic here to use a custom SQL file
        }

        $this->call('db:seed', ['--class' => CollaboratorsSeeder::class]);

        $this->info('✅ Collaborators seeding completed successfully!');

        // Show summary
        $this->showSummary();
    }

    /**
     * Clear existing collaborator data
     */
    private function clearCollaboratorData()
    {
        DB::table('contact_language_variants')->delete();

        // Only delete contacts that are collaborators (have collaborator role)
        $collaboratorUserIds = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'collaborator')
            ->pluck('users.id');

        $collaboratorContactIds = DB::table('contacts')
            ->whereIn('user_id', $collaboratorUserIds)
            ->pluck('id');

        DB::table('contacts')->whereIn('id', $collaboratorContactIds)->delete();
        DB::table('users')->whereIn('id', $collaboratorUserIds)->delete();

        $this->info('🗑️  Cleared existing collaborator data');
    }

    /**
     * Show seeding summary
     */
    private function showSummary()
    {
        $languageCount = DB::table('languages')->count();
        $variantCount = DB::table('language_variants')->count();
        $contactCount = DB::table('contacts')->count();
        $combinationCount = DB::table('contact_language_variants')->count();

        $this->info('📊 Seeding Summary:');
        $this->line("   Languages: {$languageCount}");
        $this->line("   Language Variants: {$variantCount}");
        $this->line("   Contacts: {$contactCount}");
        $this->line("   Language Combinations: {$combinationCount}");
    }
}
