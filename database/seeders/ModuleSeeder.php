<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Team;

class ModuleSeeder extends Seeder
{
    public function run()
    {
        $modules = [
            // Módulos core (1-7)
            [
                'name' => 'Dashboard',
                'key' => 'dashboard',
                'icon' => 'dashboard',
                'description' => 'Main dashboard and analytics',
                'is_core' => true,
                'status' => 1,
            ],
            [
                'name' => 'Users',
                'key' => 'users',
                'icon' => 'users',
                'description' => 'User management module',
                'is_core' => true,
                'status' => 1,
            ],
            [
                'name' => 'Settings',
                'key' => 'settings',
                'icon' => 'cog',
                'description' => 'System settings module',
                'is_core' => true,
                'status' => 1,
            ],
            [
                'name' => 'Contacts',
                'key' => 'contacts',
                'icon' => 'address-book',
                'description' => 'Contact management module',
                'is_core' => true,
                'status' => 1,
            ],
            [
                'name' => 'Enterprises',
                'key' => 'enterprises',
                'icon' => 'building',
                'description' => 'Enterprise management module',
                'is_core' => true,
                'status' => 1,
            ],
            [
                'name' => 'Projects',
                'key' => 'projects',
                'icon' => 'project-diagram',
                'description' => 'Project management module',
                'is_core' => true,
                'status' => 1,
            ],
            [
                'name' => 'Services',
                'key' => 'services',
                'icon' => 'server',
                'description' => 'Service management module',
                'is_core' => true,
                'status' => 1,
            ],
            
            // Módulos adicionales (8-18)
            [
                'name' => 'Invoices',
                'key' => 'invoices',
                'icon' => 'file-invoice',
                'description' => 'Invoice management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Payments',
                'key' => 'payments',
                'icon' => 'money-bill',
                'description' => 'Payment management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Communications',
                'key' => 'communications',
                'icon' => 'comments',
                'description' => 'Communications management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Tasks',
                'key' => 'tasks',
                'icon' => 'tasks',
                'description' => 'Task management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Notes',
                'key' => 'notes',
                'icon' => 'sticky-note',
                'description' => 'Notes management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Tickets',
                'key' => 'tickets',
                'icon' => 'ticket-alt',
                'description' => 'Support ticket management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Events',
                'key' => 'events',
                'icon' => 'calendar',
                'description' => 'Events management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Landings',
                'key' => 'landings',
                'icon' => 'pager',
                'description' => 'Landing pages management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Multimedia',
                'key' => 'multimedia',
                'icon' => 'photo-video',
                'description' => 'Multimedia files management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Marketing',
                'key' => 'marketing',
                'icon' => 'bullhorn',
                'description' => 'Marketing tools and campaigns module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Hosting',
                'key' => 'hosting',
                'icon' => 'server',
                'description' => 'Hosting management module',
                'is_core' => false,
                'status' => 1,
            ],
        ];

        foreach ($modules as $moduleData) {
            Module::firstOrCreate(
                ['key' => $moduleData['key']],
                $moduleData
            );
        }

        // Activar módulos core para todos los equipos existentes
        $teams = Team::all();
        $coreModules = Module::where('is_core', true)->get();
        
        foreach ($teams as $team) {
            foreach ($coreModules as $module) {
                $team->enableModule($module->key);
                $this->command->info("Core module {$module->name} enabled for team {$team->name}");
            }
        }
        
        // Activar todos los módulos para el equipo admin (ID = 1)
        $adminTeam = Team::find(1);
        if ($adminTeam) {
            $nonCoreModules = Module::where('is_core', false)->get();
            
            foreach ($nonCoreModules as $module) {
                $adminTeam->enableModule($module->key);
                $this->command->info("Additional module {$module->name} enabled for admin team");
            }
        }
    }
} 