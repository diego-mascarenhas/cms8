<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Services\DemoDataService;
use Illuminate\Database\Seeder;

/**
 * Demo seed — complementary to the main deploy seed.
 * Run after deploy (or after db:seed) to create demo clients, projects, and mail demo data on Team "Demo".
 * Fresh install: same data runs from {@see TeamDemoSeeder} (after mail settings).
 * Standalone: php artisan db:seed --class=DemoSeeder (requires Team "Demo" from the main seed).
 */
class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::withoutGlobalScopes()
            ->where('name', 'Demo')
            ->first();

        if (! $team)
        {
            $this->command->warn('Demo team not found. Run the main seed first: php artisan db:seed');

            return;
        }

        DemoDataService::createClientsAndProjects($team->id, $this->command);

        DemoMailCampaignData::seed($team, $this->command);

        $this->call(DemoWhatsAppConversationsSeeder::class);

        $this->call(DemoNotificationsSeeder::class);

        $this->call(DemoAffiliatesSeeder::class);

        $this->call(DemoDigestScenariosSeeder::class);

        $this->call(DemoPerformanceInsightsSeeder::class);

        $this->call(DemoDashboardRichDataSeeder::class);

        $this->call(DemoKanbanTasksSeeder::class);

        $this->call(DemoMailInboxGroupsSeeder::class);
    }
}
