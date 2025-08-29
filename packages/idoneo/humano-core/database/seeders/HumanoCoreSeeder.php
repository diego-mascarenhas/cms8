<?php

namespace Idoneo\HumanoCore\Database\Seeders;

use App\Models\Team;
use Idoneo\HumanoCore\Models\Category;
use Idoneo\HumanoCore\Models\Module;
use Illuminate\Database\Seeder;

class HumanoCoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $demoTeam = Team::where('name', 'Demo Team')->first() ?? Team::first();

        if (! $demoTeam)
        {
            return; // No teams available
        }

        // Create core modules
        $modules = [
            [
                'name' => 'Core System',
                'slug' => 'core',
                'description' => 'Core functionality for user management, teams, and system administration',
                'version' => '1.0.0',
                'is_active' => true,
                'team_id' => $demoTeam->id,
            ],
            [
                'name' => 'CRM Module',
                'slug' => 'crm',
                'description' => 'Customer relationship management with contacts and projects',
                'version' => '1.0.0',
                'is_active' => true,
                'team_id' => $demoTeam->id,
            ],
            [
                'name' => 'Billing Module',
                'slug' => 'billing',
                'description' => 'Invoice and payment management system',
                'version' => '1.0.0',
                'is_active' => true,
                'team_id' => $demoTeam->id,
            ],
            [
                'name' => 'Communications Module',
                'slug' => 'communications',
                'description' => 'Email, chat, and notification system',
                'version' => '1.0.0',
                'is_active' => true,
                'team_id' => $demoTeam->id,
            ],
            [
                'name' => 'Hosting Module',
                'slug' => 'hosting',
                'description' => 'Server and domain management system',
                'version' => '1.0.0',
                'is_active' => true,
                'team_id' => $demoTeam->id,
            ],
        ];

        foreach ($modules as $moduleData)
        {
            Module::firstOrCreate(
                ['slug' => $moduleData['slug'], 'team_id' => $moduleData['team_id']],
                $moduleData,
            );
        }

        // Create core categories
        $categories = [
            [
                'name' => 'General',
                'description' => 'General purpose category',
                'color' => '#6c757d',
                'icon' => 'ti ti-folder',
                'module_key' => 'core',
                'is_active' => true,
                'sort_order' => 1,
                'team_id' => $demoTeam->id,
            ],
            [
                'name' => 'Important',
                'description' => 'High priority items',
                'color' => '#dc3545',
                'icon' => 'ti ti-alert-triangle',
                'module_key' => 'core',
                'is_active' => true,
                'sort_order' => 2,
                'team_id' => $demoTeam->id,
            ],
            [
                'name' => 'Archive',
                'description' => 'Archived items',
                'color' => '#6c757d',
                'icon' => 'ti ti-archive',
                'module_key' => 'core',
                'is_active' => false,
                'sort_order' => 99,
                'team_id' => $demoTeam->id,
            ],
        ];

        foreach ($categories as $categoryData)
        {
            Category::firstOrCreate(
                [
                    'name' => $categoryData['name'],
                    'module_key' => $categoryData['module_key'],
                    'team_id' => $categoryData['team_id'],
                ],
                $categoryData,
            );
        }
    }
}
