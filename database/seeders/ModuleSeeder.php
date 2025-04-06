<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Team;

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
        'enterprises' => [
            'name' => 'Enterprises',
            'icon' => 'building',
            'description' => 'Enterprise management module',
        ],
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
    ];
    
    protected $additionalModules = [
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
        'marketing' => [
            'name' => 'Marketing',
            'icon' => 'bullhorn',
            'description' => 'Marketing tools and campaigns module',
        ],
        'hosting' => [
            'name' => 'Hosting',
            'icon' => 'server',
            'description' => 'Hosting management module',
        ],
    ];
    
    protected $teamModules = [
        1 => ['invoices', 'payments', 'communications', 'notes', 'tickets', 'events', 'landings', 'multimedia', 'marketing', 'hosting'],
        2 => ['invoices', 'payments', 'communications', 'tickets', 'marketing'],
        3 => ['invoices', 'payments', 'communications', 'tickets', 'marketing'],
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
                ]
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
                ]
            );
            $this->command->info("Módulo adicional '{$moduleData['name']}' creado o actualizado");
        }
        
        $teams = Team::all();
        $coreModuleObjects = Module::where('is_core', true)->get();
        
        foreach ($teams as $team) {
            $this->command->info("Habilitando módulos core para equipo '{$team->name}'");
            
            foreach ($coreModuleObjects as $module) {
                $team->enableModule($module->key);
            }
        }
        
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