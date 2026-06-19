<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    protected $coreModules = [
        'dashboard' => [
            'name' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'description' => 'Main dashboard and analytics',
            'is_enabled' => true,  // On by default
        ],
        'users' => [
            'name' => 'Users',
            'icon' => 'users',
            'description' => 'User management module',
            'is_enabled' => true,  // On by default
        ],
        'settings' => [
            'name' => 'Settings',
            'icon' => 'settings',
            'description' => 'System settings module',
            'is_enabled' => false,  // Off by default
        ],
        'contacts' => [
            'name' => 'Contacts',
            'icon' => 'users',
            'description' => 'Contact management module',
            'is_enabled' => true,  // On by default
        ],
        'clients' => [
            'name' => 'Clients',
            'icon' => 'user-heart',
            'description' => 'Client management module',
            'is_enabled' => true,  // On by default
        ],
        'list60' => [
            'name' => 'List of 60',
            'icon' => 'list-check',
            'description' => 'List of 60 management module',
            'is_enabled' => false,  // Off by default
        ],
        'services' => [
            'name' => 'Services',
            'icon' => 'rocket',
            'description' => 'Service management module',
            'is_enabled' => true,  // On by default
        ],
        'projects' => [
            'name' => 'Projects',
            'icon' => 'folder',
            'description' => 'Project management module',
            'is_enabled' => true,  // On by default
        ],
        'opportunities' => [
            'name' => 'Opportunities',
            'icon' => 'chart-donut',
            'description' => 'CRM opportunities and pipeline',
            'is_enabled' => true,
        ],
        'tasks' => [
            'name' => 'Tasks',
            'icon' => 'layout-kanban',
            'description' => 'Task management module',
            'is_enabled' => true,  // On by default
        ],
        'calendar' => [
            'name' => 'Calendar',
            'icon' => 'calendar',
            'description' => 'Calendar and appointments module',
            'is_enabled' => true,  // On by default
        ],
        'notifications' => [
            'name' => 'Notifications',
            'icon' => 'speakerphone',
            'description' => 'Notifications and alerts module',
            'is_enabled' => true,  // On by default
        ],
    ];

    protected $additionalModules = [
        // BILLING GROUP
        'subscriptions' => [
            'name' => 'Subscriptions',
            'icon' => 'repeat',
            'description' => 'Subscriptions and billing plans module',
            'group' => 'billing',
            'order' => 0,
            'is_enabled' => true,  // On by default
        ],
        'invoices' => [
            'name' => 'Invoices',
            'icon' => 'file-invoice',
            'description' => 'Invoice management module',
            'group' => 'billing',
            'order' => 1,
            'is_enabled' => true,  // On by default
        ],
        'payments' => [
            'name' => 'Payments',
            'icon' => 'credit-card',
            'description' => 'Payment management module',
            'group' => 'billing',
            'order' => 2,
            'is_enabled' => true,  // On by default
        ],
        'accounting' => [
            'name' => 'Stripe billing',
            'icon' => 'calculator',
            'description' => 'Stripe invoices, PDF downloads and quarterly CSV exports',
            'group' => 'billing',
            'order' => 3,
        ],
        'financial' => [
            'name' => 'Financial',
            'icon' => 'chart-line',
            'description' => 'Financial evolution module',
            'group' => 'billing',
            'order' => 4,
        ],
        'incomes' => [
            'name' => 'Incomes',
            'icon' => 'trending-up',
            'description' => 'Incomes management module',
            'group' => 'billing',
            'order' => 5,
        ],
        'expenses' => [
            'name' => 'Expenses',
            'icon' => 'trending-down',
            'description' => 'Expenses management module',
            'group' => 'billing',
            'order' => 6,
        ],
        'enterprises' => [
            'name' => 'Enterprises',
            'icon' => 'building',
            'description' => 'Enterprise management module',
            'group' => 'billing',
            'order' => 7,
        ],
        'affiliates' => [
            'name' => 'Affiliates',
            'icon' => 'affiliate',
            'description' => 'Referral commissions on billing (enterprise referred_by and team commission %)',
            'group' => 'billing',
            'order' => 8,
        ],
        // ECOMMERCE GROUP
        'stores' => [
            'name' => 'Stores',
            'icon' => 'building-store',
            'description' => 'Online stores management module',
            'group' => 'ecommerce',
            'order' => 3,
        ],
        'products' => [
            'name' => 'Products',
            'icon' => 'package',
            'description' => 'Products management module',
            'group' => 'ecommerce',
            'order' => 1,
        ],
        'orders' => [
            'name' => 'Orders',
            'icon' => 'shopping-bag',
            'description' => 'Orders management module',
            'group' => 'ecommerce',
            'order' => 2,
        ],
        // INFRASTRUCTURE GROUP
        'servers' => [
            'name' => 'Servers',
            'icon' => 'server',
            'description' => 'Server management module',
            'group' => 'infrastructure',
            'order' => 1,
        ],
        'hosting' => [
            'name' => 'Hosting',
            'icon' => 'server-2',
            'description' => 'Hosting accounts management module',
            'group' => 'infrastructure',
            'order' => 2,
        ],
        'notes' => [
            'name' => 'Notes',
            'icon' => 'note',
            'description' => 'Notes management module',
            'order' => 7,
        ],
        'communications' => [
            'name' => 'Communications',
            'icon' => 'message-circle',
            'description' => 'Communications management module',
            'order' => 8,
        ],
        // CAMPAIGNS GROUP (Marketing)
        'campaigns' => [
            'name' => 'Campaigns',
            'icon' => 'broadcast',
            'description' => 'Campaign messages and scheduled sends (email, WhatsApp, etc.)',
            'group' => 'campaigns',
            'order' => 0,
        ],
        'mailer' => [
            'name' => 'Mailer',
            'icon' => 'send',
            'description' => 'Email campaigns and marketing automation',
            'group' => 'campaigns',
            'order' => 1,
        ],
        'templates' => [
            'name' => 'Templates',
            'icon' => 'template',
            'description' => 'Templates management module',
            'group' => 'campaigns',
            'order' => 2,
        ],
        // AUTOMATION GROUP
        'funnel' => [
            'name' => 'Funnel',
            'icon' => 'filter',
            'description' => 'Sales funnel module',
            'group' => 'automation',
            'order' => 2,
        ],
        'integrations' => [
            'name' => 'API',
            'icon' => 'api',
            'description' => 'API integrations management module',
            'group' => 'automation',
            'order' => 3,
        ],
        'prompts' => [
            'name' => 'Prompts',
            'icon' => 'cpu',
            'description' => 'Instructions for the assistant.',
            'group' => 'automation',
            'order' => 1,
        ],
        'ocr' => [
            'name' => 'OCR',
            'icon' => 'scan',
            'description' => 'AI text extraction from PDFs and images',
            'group' => 'automation',
            'order' => 4,
        ],
        // CONTENT GROUP
        'multimedia' => [
            'name' => 'Multimedia',
            'icon' => 'photo',
            'description' => 'Multimedia files management module',
            'group' => 'content',
            'order' => 1,
        ],
        'cms' => [
            'name' => 'CMS',
            'icon' => 'file-text',
            'description' => 'WordPress-like content management (posts, pages, taxonomies)',
            'group' => 'content',
            'order' => 2,
        ],
        'website' => [
            'name' => 'Sitio web',
            'icon' => 'world',
            'description' => 'WordPress posts and pages, landing page',
            'group' => 'content',
            'order' => 3,
        ],
        'academy' => [
            'name' => 'Academy',
            'icon' => 'book',
            'description' => 'Academy courses management module',
            'group' => 'content',
            'order' => 4,
        ],
        'landings' => [
            'name' => 'Landings',
            'icon' => 'globe',
            'description' => 'Landing pages management module',
            'group' => 'content',
            'order' => 5,
        ],
        'team_files' => [
            'name' => 'Team files',
            'icon' => 'folders',
            'description' => 'Team company files and brand assets',
            'group' => 'content',
            'order' => 6,
        ],
        'blog' => [
            'name' => 'Blog',
            'icon' => 'article',
            'description' => 'Blog articles and posts management',
            'group' => 'content',
            'order' => 7,
        ],
        'ebooks' => [
            'name' => 'E-books',
            'icon' => 'book-2',
            'description' => 'Digital books and downloadable publications',
            'group' => 'content',
            'order' => 8,
        ],
        // SUPPORT & TICKETS
        'tickets' => [
            'name' => 'Tickets',
            'icon' => 'ticket',
            'description' => 'Support ticket management module',
            'group' => 'support',
            'order' => 1,
        ],
        'mailbox' => [
            'name' => 'Mailbox',
            'icon' => 'mail',
            'description' => 'Team mailbox management',
            'group' => 'support',
            'order' => 2,
        ],
        'chat' => [
            'name' => 'Chat',
            'icon' => 'lifebuoy',
            'description' => 'Live chat module',
            'group' => 'support',
            'order' => 3,
        ],
        // GENERAL MANAGEMENT
        'prospecting' => [
            'name' => 'Prospecting',
            'icon' => 'target',
            'description' => 'Prospect search and contact acquisition',
            'order' => 1,
        ],
        'events' => [
            'name' => 'Events',
            'icon' => 'calendar-event',
            'description' => 'Events management module',
            'order' => 2,
        ],
        'today' => [
            'name' => 'Today',
            'icon' => 'calendar-event',
            'description' => "Today's activities module",
            'order' => 3,
        ],
        'times' => [
            'name' => 'Times',
            'icon' => 'hourglass',
            'description' => 'Time tracking module',
            'order' => 4,
            'is_enabled' => true,  // On by default
        ],
        'attendances' => [
            'name' => 'Attendance',
            'icon' => 'clock',
            'description' => 'Attendance tracking module',
            'order' => 5,
        ],
        'documentation' => [
            'name' => 'Documentation',
            'icon' => 'files',
            'description' => 'Documentation management module',
            'order' => 6,
        ],
        'departments' => [
            'name' => 'Departments',
            'icon' => 'hierarchy',
            'description' => 'Department management module',
            'order' => 7,
        ],
        'collaborators' => [
            'name' => 'Collaborators',
            'icon' => 'users-group',
            'description' => 'Collaborators management module',
            'order' => 8,
        ],
        'survival' => [
            'name' => 'Survival',
            'icon' => 'shield',
            'description' => 'Survival management module',
            'order' => 9,
        ],
        'performance_insights' => [
            'name' => 'Team performance insights',
            'icon' => 'chart-infographic',
            'description' => 'Daily performance insights and admin team metrics list',
            'order' => 10,
        ],
        // INNOVATION GROUP
        'proposals' => [
            'name' => 'Proposals',
            'icon' => 'bulb',
            'description' => 'Innovation proposals management module',
            'group' => 'innovation',
            'order' => 1,
        ],
        'challenges' => [
            'name' => 'Challenges',
            'icon' => 'puzzle',
            'description' => 'Innovation challenges management module',
            'group' => 'innovation',
            'order' => 2,
        ],
        // SECURITY GROUP
        'passwords' => [
            'name' => 'Passwords',
            'icon' => 'key',
            'description' => 'Password vault and credential management module',
            'group' => 'security',
            'order' => 1,
        ],
        'canary_tokens' => [
            'name' => 'Canary Tokens',
            'icon' => 'shield-lock',
            'description' => 'Canary token generation and monitoring module',
            'group' => 'security',
            'order' => 2,
        ],
        // LEARNING & DEVELOPMENT
        'languages' => [
            'name' => 'Languages',
            'icon' => 'language',
            'description' => 'Languages management module',
            'group' => 'learning',
            'order' => 1,
        ],
        'language-variants' => [
            'name' => 'Language Variants',
            'icon' => 'letter-case-upper',
            'description' => 'Language variants management module',
            'group' => 'learning',
            'order' => 2,
        ],
        'fares' => [
            'name' => 'Fares',
            'icon' => 'currency-dollar',
            'description' => 'Fares management module',
            'group' => 'learning',
            'order' => 3,
        ],
        'softwares' => [
            'name' => 'Software',
            'icon' => 'device-laptop',
            'description' => 'Software management module',
            'group' => 'learning',
            'order' => 4,
        ],
        'certifications' => [
            'name' => 'Certifications',
            'icon' => 'certificate',
            'description' => 'Certifications management module',
            'group' => 'learning',
            'order' => 5,
        ],
        'stylebooks' => [
            'name' => 'Stylebooks',
            'icon' => 'palette',
            'description' => 'Stylebooks management module',
            'group' => 'learning',
            'order' => 6,
        ],
        'exams' => [
            'name' => 'Exams',
            'icon' => 'clipboard-check',
            'description' => 'Learning exams and assessments module',
            'group' => 'learning',
            'order' => 7,
        ],
    ];

    protected $teamModules = [
        1 => ['invoices', 'payments', 'communications', 'notes', 'tickets', 'events', 'landings', 'multimedia', 'team_files', 'blog', 'ebooks', 'website', 'campaigns', 'templates', 'mailer', 'hosting', 'mail', 'chat', 'enterprises', 'affiliates', 'prospecting', 'projects', 'services', 'times', 'documentation', 'ocr', 'incomes', 'expenses', 'financial', 'departments', 'funnel', 'automations', 'integrations', 'products', 'orders', 'academy'],
    ];

    public function run()
    {
        $this->command->info('Creando módulos...');

        foreach ($this->coreModules as $key => $moduleData)
        {
            Module::updateOrCreate(
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

        foreach ($this->additionalModules as $key => $moduleData)
        {
            Module::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $moduleData['name'],
                    'key' => $key,
                    'icon' => $moduleData['icon'],
                    'description' => $moduleData['description'],
                    'is_core' => false,
                    'group' => $moduleData['group'] ?? null,
                    'order' => $moduleData['order'] ?? 0,
                    'status' => 1,
                ],
            );
            $groupLabel = isset($moduleData['group']) ? " [{$moduleData['group']}]" : '';
            $this->command->info("Módulo adicional '{$moduleData['name']}'{$groupLabel} creado o actualizado");
        }

        // Note: Module enablement is now handled in UserSeeder after team creation
        // This ensures correct application of is_enabled flag for core modules

        $this->command->info('Configuración de módulos completada.');
    }
}
