<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CoreModulesPermissionsSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$this->command->info('🔐 Creating core modules permissions...');

		// Define all core modules and their permissions
		$coreModules = [
			'dashboard' => ['index'],
			'users' => ['list', 'create', 'show', 'edit', 'store', 'update', 'destroy'],
			'settings' => ['index', 'edit', 'update'],
			'contacts' => ['list', 'create', 'show', 'edit', 'store', 'update', 'destroy'],
			'clients' => ['list', 'create', 'show', 'edit', 'store', 'update', 'destroy'],
			'list60' => ['list', 'create', 'show', 'edit', 'store', 'update', 'destroy'],
			'services' => ['list', 'create', 'show', 'edit', 'store', 'update', 'destroy'],
			'projects' => ['list', 'create', 'show', 'edit', 'store', 'update', 'destroy'],
			'tasks' => ['list', 'create', 'show', 'edit', 'store', 'update', 'destroy'],
			'templates' => ['list', 'create', 'show', 'edit', 'store', 'update', 'destroy'],
			'notifications' => ['list', 'create', 'show', 'edit', 'store', 'update', 'destroy'],
			// Billing-related modules are owned by humano-billing package
			// and their permissions are created in that package's service provider.
		];

		$permissionsCreated = 0;

		// Create permissions for each module
		foreach ($coreModules as $module => $actions) {
			foreach ($actions as $action) {
				$permissionName = $module . '.' . $action;
				Permission::firstOrCreate(['name' => $permissionName]);
				$permissionsCreated++;
			}
		}

		// Create additional permissions that might be needed
		$additionalPermissions = [
			'today.index',
			'today.list',
			'time.list',
			'time.create',
			'time.show',
			'time.edit',
			'time.store',
			'time.update',
			'time.destroy',
			'invoice.list',
			'invoice.create',
			'invoice.show',
			'invoice.edit',
			'invoice.store',
			'invoice.update',
			'invoice.destroy',
			'payment.list',
			'payment.create',
			'payment.show',
			'payment.edit',
			'payment.store',
			'payment.update',
			'payment.destroy',
			'attendance.list',
			'attendance.create',
			'attendance.show',
			'attendance.edit',
			'attendance.store',
			'attendance.update',
			'attendance.destroy',
			'documentation.list',
			'documentation.create',
			'documentation.show',
			'documentation.edit',
			'documentation.store',
			'documentation.update',
			'documentation.destroy',
			'department.list',
			'department.create',
			'department.show',
			'department.edit',
			'department.store',
			'department.update',
			'department.destroy',
			'funnel.list',
			'funnel.create',
			'funnel.show',
			'funnel.edit',
			'funnel.store',
			'funnel.update',
			'funnel.destroy',
			'automation.list',
			'automation.create',
			'automation.show',
			'automation.edit',
			'automation.store',
			'automation.update',
			'automation.destroy',
			'integration.list',
			'integration.create',
			'integration.show',
			'integration.edit',
			'integration.store',
			'integration.update',
			'integration.destroy',
			'message.list',
			'message.create',
			'message.show',
			'message.edit',
			'message.store',
			'message.update',
			'message.destroy',
			'hosting.index',
			'hosting.list',
			'hosting.create',
			'hosting.show',
			'hosting.edit',
			'hosting.store',
			'hosting.update',
			'hosting.destroy',
			'academy.list',
			'academy.create',
			'academy.show',
			'academy.edit',
			'academy.store',
			'academy.update',
			'academy.destroy',
			'template.list',
			'template.create',
			'template.show',
			'template.edit',
			'template.store',
			'template.update',
			'template.destroy',
		];

		foreach ($additionalPermissions as $permission) {
			Permission::firstOrCreate(['name' => $permission]);
			$permissionsCreated++;
		}

		$this->command->info("✅ Created {$permissionsCreated} core module permissions");

		// Assign all permissions to admin role
		$adminRole = Role::where('name', 'admin')->first();
		if ($adminRole) {
			$allPermissions = Permission::all()->pluck('name')->toArray();
			$adminRole->syncPermissions($allPermissions);
			$this->command->info('✅ Assigned all permissions to admin role');
		} else {
			$this->command->warn('⚠️  Admin role not found');
		}
	}
}
