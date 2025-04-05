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
                'name' => 'Enterprises',
                'key' => 'enterprises',
                'icon' => 'building',
                'description' => 'Enterprise management module',
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
                'name' => 'Projects',
                'key' => 'projects',
                'icon' => 'project-diagram',
                'description' => 'Project management module',
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
                'name' => 'Services',
                'key' => 'services',
                'icon' => 'server',
                'description' => 'Service management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Invoices',
                'key' => 'invoices',
                'icon' => 'file-invoice',
                'description' => 'Invoice management module',
                'is_core' => false,
                'status' => 1,
            ],
            [
                'name' => 'Accounting',
                'key' => 'accounting',
                'icon' => 'money-bill',
                'description' => 'Accounting module',
                'is_core' => false,
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
        ];

        foreach ($modules as $moduleData) {
            Module::firstOrCreate(
                ['key' => $moduleData['key']],
                $moduleData
            );
        }

        // Activate all modules for admin team
        $adminTeam = Team::find(1); // Assuming admin team has ID 1
        
        if ($adminTeam) {
            $modules = Module::all();
            
            foreach ($modules as $module) {
                $adminTeam->enableModule($module->key);
            }
        }
    }
} 