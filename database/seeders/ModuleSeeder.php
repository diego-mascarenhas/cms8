<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    protected $coreModules = [
        'dashboard' => [
            'name' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'description' => 'Main dashboard and analytics',
        ],
        'users' => [
            'name' => 'Users',
            'icon' => 'users',
            'description' => 'User management module',
        ],
        'settings' => [
            'name' => 'Settings',
            'icon' => 'settings',
            'description' => 'System settings module',
        ],
        'tasks' => [
            'name' => 'Tasks',
            'icon' => 'checklist',
            'description' => 'Task management module',
        ],
        'clients' => [
            'name' => 'Clients',
            'icon' => 'user-heart',
            'description' => 'Client management module',
        ],
        'list60' => [
            'name' => 'List of 60',
            'icon' => 'list-check',
            'description' => 'List of 60 management module',
        ],
    ];

    protected $additionalModules = [
        'contacts' => [
            'name' => 'Contacts',
            'icon' => 'address-book',
            'description' => 'Contact management module',
        ],
        'collaborators' => [
            'name' => 'Collaborators',
            'icon' => 'users-group',
            'description' => 'Collaborators management module',
        ],
        'projects' => [
            'name' => 'Projects',
            'icon' => 'folder',
            'description' => 'Project management module',
        ],
        'services' => [
            'name' => 'Services',
            'icon' => 'server',
            'description' => 'Service management module',
        ],
        'enterprises' => [
            'name' => 'Enterprises',
            'icon' => 'building',
            'description' => 'Enterprise management module',
        ],
        'invoices' => [
            'name' => 'Invoices',
            'icon' => 'file-invoice',
            'description' => 'Invoice management module',
        ],
        'payments' => [
            'name' => 'Payments',
            'icon' => 'credit-card',
            'description' => 'Payment management module',
        ],
        'communications' => [
            'name' => 'Communications',
            'icon' => 'message-circle',
            'description' => 'Communications management module',
        ],
        'notes' => [
            'name' => 'Notes',
            'icon' => 'note',
            'description' => 'Notes management module',
        ],
        'tickets' => [
            'name' => 'Tickets',
            'icon' => 'ticket',
            'description' => 'Support ticket management module',
        ],
        'events' => [
            'name' => 'Events',
            'icon' => 'calendar',
            'description' => 'Events management module',
        ],
        'landings' => [
            'name' => 'Landings',
            'icon' => 'page-break',
            'description' => 'Landing pages management module',
        ],
        'multimedia' => [
            'name' => 'Multimedia',
            'icon' => 'photo',
            'description' => 'Multimedia files management module',
        ],
        'website' => [
            'name' => 'Website',
            'icon' => 'world',
            'description' => 'Website module',
        ],
        'hosting' => [
            'name' => 'Hosting',
            'icon' => 'server',
            'description' => 'Hosting management module',
        ],
        'mail' => [
            'name' => 'Mail',
            'icon' => 'mail',
            'description' => 'Email management module',
        ],
        'chat' => [
            'name' => 'Chat',
            'icon' => 'message-circle',
            'description' => 'Live chat module',
        ],
        'today' => [
            'name' => 'Today',
            'icon' => 'calendar-time',
            'description' => 'Today\'s activities module',
        ],
        'times' => [
            'name' => 'Times',
            'icon' => 'hourglass',
            'description' => 'Time tracking module',
        ],
        'documentation' => [
            'name' => 'Documentation',
            'icon' => 'files',
            'description' => 'Documentation management module',
        ],
        'earnings' => [
            'name' => 'Earnings',
            'icon' => 'coin',
            'description' => 'Earnings management module',
        ],
        'expenses' => [
            'name' => 'Expenses',
            'icon' => 'receipt',
            'description' => 'Expenses management module',
        ],
        'accounting' => [
            'name' => 'Accounting',
            'icon' => 'calculator',
            'description' => 'Accounting management module',
        ],
        'financial' => [
            'name' => 'Financial',
            'icon' => 'chart-line',
            'description' => 'Financial evolution module',
        ],
        'departments' => [
            'name' => 'Departments',
            'icon' => 'users-group',
            'description' => 'Department management module',
        ],
        'funnel' => [
            'name' => 'Funnel',
            'icon' => 'filter',
            'description' => 'Sales funnel module',
        ],
        'automations' => [
            'name' => 'Automations',
            'icon' => 'robot',
            'description' => 'Automation management module',
        ],
        'integrations' => [
            'name' => 'Integrations',
            'icon' => 'api',
            'description' => 'Integrations management module',
        ],
        'campaigns' => [
            'name' => 'Campaigns',
            'icon' => 'send',
            'description' => 'Campaigns management module',
        ],
        'templates' => [
            'name' => 'Templates',
            'icon' => 'template',
            'description' => 'Templates management module',
        ],
        'languages' => [
            'name' => 'Languages',
            'icon' => 'language',
            'description' => 'Languages management module',
        ],
        'language-variants' => [
            'name' => 'Language Variants',
            'icon' => 'language',
            'description' => 'Language variants management module',
        ],
        'fares' => [
            'name' => 'Fares',
            'icon' => 'currency-dollar',
            'description' => 'Fares management module',
        ],
        'softwares' => [
            'name' => 'Software',
            'icon' => 'device-laptop',
            'description' => 'Software management module',
        ],
        'certifications' => [
            'name' => 'Certifications',
            'icon' => 'certificate',
            'description' => 'Certifications management module',
        ],
        'stylebooks' => [
            'name' => 'Stylebooks',
            'icon' => 'book',
            'description' => 'Stylebooks management module',
        ],
        'notifications' => [
            'name' => 'Notifications',
            'icon' => 'bell',
            'description' => 'Notifications and alerts module',
        ],
    ];

    protected $teamModules = [
        1 => ['invoices', 'payments', 'communications', 'notes', 'tickets', 'events', 'landings', 'multimedia', 'marketing', 'hosting', 'mail', 'chat', 'enterprises', 'projects', 'services', 'times', 'documentation', 'earnings', 'expenses', 'accounting', 'financial', 'departments', 'funnel', 'automations', 'integrations', 'campaigns'],
        2 => ['invoices', 'payments', 'communications', 'tickets', 'marketing', 'enterprises', 'projects', 'services', 'times', 'documentation', 'earnings', 'expenses', 'accounting', 'financial', 'departments', 'funnel'],
    ];

    public function run()
    {
        $this->command->info('Creando módulos...');

        foreach ($this->coreModules as $key => $moduleData) {
            Module::firstOrCreate(
                ['key' => $key],
                [
                    'name' => $moduleData['name'],
                    'key' => $key,
                    'icon' => $moduleData['icon'],
                    'description' => $moduleData['description'],
                    'is_core' => true,
                    'status' => 1,
                ],
            );
            $this->command->info("Módulo core '{$moduleData['name']}' creado o actualizado");
        }

        foreach ($this->additionalModules as $key => $moduleData) {
            Module::firstOrCreate(
                ['key' => $key],
                [
                    'name' => $moduleData['name'],
                    'key' => $key,
                    'icon' => $moduleData['icon'],
                    'description' => $moduleData['description'],
                    'is_core' => false,
                    'status' => 1,
                ],
            );
            $this->command->info("Módulo adicional '{$moduleData['name']}' creado o actualizado");
        }

        // Enable core modules for all teams
        $teams = Team::all();
        $coreModuleObjects = Module::where('is_core', true)->get();

        foreach ($teams as $team) {
            $this->command->info("Habilitando módulos core para equipo '{$team->name}'");

            foreach ($coreModuleObjects as $module) {
                $team->enableModule($module->key);
                $this->command->info("- Módulo core '{$module->name}' habilitado");
            }
        }

        // Enable additional modules for specific teams
        foreach ($this->teamModules as $teamId => $moduleKeys) {
            $team = Team::find($teamId);

            if ($team) {
                $this->command->info("Habilitando módulos adicionales para equipo '{$team->name}'");

                foreach ($moduleKeys as $moduleKey) {
                    $module = Module::where('key', $moduleKey)->first();

                    if ($module) {
                        $team->enableModule($moduleKey);
                        $this->command->info("- Módulo '{$module->name}' habilitado");
                    }
                }
            }
        }

        $this->command->info('Configuración de módulos completada.');
    }
}
