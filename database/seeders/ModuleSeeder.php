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
            'icon' => 'dashboard',
            'description' => 'Main dashboard and analytics',
        ],
        'users' => [
            'name' => 'Users',
            'icon' => 'users',
            'description' => 'User management module',
        ],
        'settings' => [
            'name' => 'Settings',
            'icon' => 'cog',
            'description' => 'System settings module',
        ],
        'tasks' => [
            'name' => 'Tasks',
            'icon' => 'tasks',
            'description' => 'Task management module',
        ],
        'contacts' => [
            'name' => 'Contacts',
            'icon' => 'address-book',
            'description' => 'Contact management module',
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
        'projects' => [
            'name' => 'Projects',
            'icon' => 'project-diagram',
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
            'icon' => 'money-bill',
            'description' => 'Payment management module',
        ],
        'communications' => [
            'name' => 'Communications',
            'icon' => 'comments',
            'description' => 'Communications management module',
        ],
        'notes' => [
            'name' => 'Notes',
            'icon' => 'sticky-note',
            'description' => 'Notes management module',
        ],
        'tickets' => [
            'name' => 'Tickets',
            'icon' => 'ticket-alt',
            'description' => 'Support ticket management module',
        ],
        'events' => [
            'name' => 'Events',
            'icon' => 'calendar',
            'description' => 'Events management module',
        ],
        'landings' => [
            'name' => 'Landings',
            'icon' => 'pager',
            'description' => 'Landing pages management module',
        ],
        'multimedia' => [
            'name' => 'Multimedia',
            'icon' => 'photo-video',
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
            'icon' => 'envelope',
            'description' => 'Email management module',
        ],
        'chat' => [
            'name' => 'Chat',
            'icon' => 'comment-dots',
            'description' => 'Live chat module',
        ],
        'today' => [
            'name' => 'Today',
            'icon' => 'calendar-time',
            'description' => 'Today\'s activities module',
        ],
        'times' => [
            'name' => 'Times',
            'icon' => 'hourglass-low',
            'description' => 'Time tracking module',
        ],
        'documentation' => [
            'name' => 'Documentation',
            'icon' => 'files',
            'description' => 'Documentation management module',
        ],
        'earnings' => [
            'name' => 'Earnings',
            'icon' => 'moneybag',
            'description' => 'Earnings management module',
        ],
        'expenses' => [
            'name' => 'Expenses',
            'icon' => 'receipt',
            'description' => 'Expenses management module',
        ],
        'accounting' => [
            'name' => 'Accounting',
            'icon' => 'receipt-tax',
            'description' => 'Accounting management module',
        ],
        'financial' => [
            'name' => 'Financial',
            'icon' => 'graph',
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
            'icon' => 'api-app',
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
            'description' => 'Fares and pricing management module',
        ],
        'softwares' => [
            'name' => 'Softwares',
            'icon' => 'cpu',
            'description' => 'Software management module',
        ],
        'certifications' => [
            'name' => 'Certifications',
            'icon' => 'award',
            'description' => 'Certifications management module',
        ],
        'stylebooks' => [
            'name' => 'Stylebooks',
            'icon' => 'book',
            'description' => 'Stylebooks and guidelines module',
        ],
        'notifications' => [
            'name' => 'Notifications',
            'icon' => 'bell',
            'description' => 'Notifications and alerts module',
        ],
        'collaborators' => [
            'name' => 'Collaborators',
            'icon' => 'user-group',
            'description' => 'Collaborators management module',
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

        // $teams = Team::all();
        // $coreModuleObjects = Module::where('is_core', true)->get();

        // foreach ($teams as $team) {
        //     $this->command->info("Habilitando módulos core para equipo '{$team->name}'");

        //     foreach ($coreModuleObjects as $module) {
        //         $team->enableModule($module->key);
        //     }
        // }

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
