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
			'icon' => 'address-book',
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
			'icon' => 'server',
			'description' => 'Service management module',
			'is_enabled' => true,  // On by default
		],
		'projects' => [
			'name' => 'Projects',
			'icon' => 'folder',
			'description' => 'Project management module',
			'is_enabled' => true,  // On by default
		],
		'tasks' => [
			'name' => 'Tasks',
			'icon' => 'layout-kanban',
			'description' => 'Task management module',
			'is_enabled' => true,  // On by default
		],
		'templates' => [
			'name' => 'Templates',
			'icon' => 'template',
			'description' => 'Templates management module',
			'is_enabled' => false,  // Off by default
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
		'invoices' => [
			'name' => 'Invoices',
			'icon' => 'file-invoice',
			'description' => 'Invoice management module',
			'group' => 'billing',
			'order' => 1,
		],
		'payments' => [
			'name' => 'Payments',
			'icon' => 'credit-card',
			'description' => 'Payment management module',
			'group' => 'billing',
			'order' => 2,
		],
		'accounting' => [
			'name' => 'Accounting',
			'icon' => 'calculator',
			'description' => 'Accounting management module',
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
		'earnings' => [
			'name' => 'Earnings',
			'icon' => 'coin',
			'description' => 'Earnings management module',
			'group' => 'billing',
			'order' => 5,
		],
		'expenses' => [
			'name' => 'Expenses',
			'icon' => 'receipt',
			'description' => 'Expenses management module',
			'group' => 'billing',
			'order' => 6,
		],
		// ECOMMERCE GROUP
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
		'stores' => [
			'name' => 'Stores',
			'icon' => 'building-store',
			'description' => 'Online stores management module',
			'group' => 'ecommerce',
			'order' => 3,
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
		'collaborators' => [
			'name' => 'Collaborators',
			'icon' => 'users-group',
			'description' => 'Collaborators management module',
			'order' => 8,
		],
		'communications' => [
			'name' => 'Communications',
			'icon' => 'message-circle',
			'description' => 'Communications management module',
			'order' => 9,
		],
		// CAMPAIGNS GROUP
		'mailer' => [
			'name' => 'Mailer',
			'icon' => 'send',
			'description' => 'Email campaigns and marketing automation',
			'group' => 'campaigns',
			'order' => 1,
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
		// CONTENT GROUP
		'multimedia' => [
			'name' => 'Multimedia',
			'icon' => 'photo',
			'description' => 'Multimedia files management module',
			'group' => 'content',
			'order' => 1,
		],
		'academy' => [
			'name' => 'Academy',
			'icon' => 'book',
			'description' => 'Academy courses management module',
			'group' => 'content',
			'order' => 2,
		],
		'landings' => [
			'name' => 'Landings',
			'icon' => 'page-break',
			'description' => 'Landing pages management module',
			'group' => 'content',
			'order' => 3,
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
		'enterprises' => [
			'name' => 'Enterprises',
			'icon' => 'building',
			'description' => 'Enterprise management module',
			'order' => 1,
		],
		'events' => [
			'name' => 'Events',
			'icon' => 'calendar',
			'description' => 'Events management module',
			'order' => 2,
		],
		'today' => [
			'name' => 'Today',
			'icon' => 'calendar-time',
			'description' => "Today's activities module",
			'order' => 3,
		],
		'times' => [
			'name' => 'Times',
			'icon' => 'hourglass',
			'description' => 'Time tracking module',
			'order' => 4,
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
			'icon' => 'users-group',
			'description' => 'Department management module',
			'order' => 7,
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
			'icon' => 'language',
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
			'icon' => 'book',
			'description' => 'Stylebooks management module',
			'group' => 'learning',
			'order' => 6,
		],
	];

	protected $teamModules = [
		1 => ['invoices', 'payments', 'communications', 'notes', 'tickets', 'events', 'landings', 'multimedia', 'marketing', 'hosting', 'mail', 'chat', 'enterprises', 'projects', 'services', 'times', 'documentation', 'earnings', 'expenses', 'financial', 'departments', 'funnel', 'automations', 'integrations', 'products', 'orders', 'academy'],
	];

	public function run()
	{
		$this->command->info('Creando módulos...');

		foreach ($this->coreModules as $key => $moduleData) {
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

		foreach ($this->additionalModules as $key => $moduleData) {
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
