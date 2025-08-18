<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
	public function run()
	{
		Permission::firstOrCreate(['name' => 'user.management']);
		Permission::firstOrCreate(['name' => 'profile.show']);
		Permission::firstOrCreate(['name' => 'profile.edit']);
		Permission::firstOrCreate(['name' => 'profile.update']);
		Permission::firstOrCreate(['name' => 'password.update']);

		Permission::firstOrCreate(['name' => 'user.index']);
		Permission::firstOrCreate(['name' => 'user.list']);
		Permission::firstOrCreate(['name' => 'user.create']);
		Permission::firstOrCreate(['name' => 'user.show']);
		Permission::firstOrCreate(['name' => 'user.edit']);
		Permission::firstOrCreate(['name' => 'user.store']);
		Permission::firstOrCreate(['name' => 'user.update']);
		Permission::firstOrCreate(['name' => 'user.destroy']);

		Permission::firstOrCreate(['name' => 'today.index']);
		Permission::firstOrCreate(['name' => 'today.list']);
		Permission::firstOrCreate(['name' => 'today.create']);
		Permission::firstOrCreate(['name' => 'today.show']);
		Permission::firstOrCreate(['name' => 'today.edit']);
		Permission::firstOrCreate(['name' => 'today.store']);
		Permission::firstOrCreate(['name' => 'today.update']);
		Permission::firstOrCreate(['name' => 'today.destroy']);

		Permission::firstOrCreate(['name' => 'chat.index']);
		Permission::firstOrCreate(['name' => 'chat.list']);
		Permission::firstOrCreate(['name' => 'chat.create']);
		Permission::firstOrCreate(['name' => 'chat.show']);
		Permission::firstOrCreate(['name' => 'chat.edit']);
		Permission::firstOrCreate(['name' => 'chat.store']);
		Permission::firstOrCreate(['name' => 'chat.update']);
		Permission::firstOrCreate(['name' => 'chat.destroy']);

		Permission::firstOrCreate(['name' => 'mail.index']);
		Permission::firstOrCreate(['name' => 'mail.list']);
		Permission::firstOrCreate(['name' => 'mail.create']);
		Permission::firstOrCreate(['name' => 'mail.show']);
		Permission::firstOrCreate(['name' => 'mail.edit']);
		Permission::firstOrCreate(['name' => 'mail.store']);
		Permission::firstOrCreate(['name' => 'mail.update']);
		Permission::firstOrCreate(['name' => 'mail.destroy']);

		Permission::firstOrCreate(['name' => 'service.index']);
		Permission::firstOrCreate(['name' => 'service.list']);
		Permission::firstOrCreate(['name' => 'service.create']);
		Permission::firstOrCreate(['name' => 'service.show']);
		Permission::firstOrCreate(['name' => 'service.edit']);
		Permission::firstOrCreate(['name' => 'service.store']);
		Permission::firstOrCreate(['name' => 'service.update']);
		Permission::firstOrCreate(['name' => 'service.destroy']);

		Permission::firstOrCreate(['name' => 'contact.index']);
		Permission::firstOrCreate(['name' => 'contact.list']);
		Permission::firstOrCreate(['name' => 'contact.create']);
		Permission::firstOrCreate(['name' => 'contact.show']);
		Permission::firstOrCreate(['name' => 'contact.edit']);
		Permission::firstOrCreate(['name' => 'contact.store']);
		Permission::firstOrCreate(['name' => 'contact.update']);
		Permission::firstOrCreate(['name' => 'contact.destroy']);

		Permission::firstOrCreate(['name' => 'collaborator.index']);
		Permission::firstOrCreate(['name' => 'collaborator.list']);
		Permission::firstOrCreate(['name' => 'collaborator.create']);
		Permission::firstOrCreate(['name' => 'collaborator.show']);
		Permission::firstOrCreate(['name' => 'collaborator.edit']);
		Permission::firstOrCreate(['name' => 'collaborator.store']);
		Permission::firstOrCreate(['name' => 'collaborator.update']);
		Permission::firstOrCreate(['name' => 'collaborator.destroy']);

		Permission::firstOrCreate(['name' => 'client.index']);
		Permission::firstOrCreate(['name' => 'client.list']);
		Permission::firstOrCreate(['name' => 'client.create']);
		Permission::firstOrCreate(['name' => 'client.show']);
		Permission::firstOrCreate(['name' => 'client.edit']);
		Permission::firstOrCreate(['name' => 'client.store']);
		Permission::firstOrCreate(['name' => 'client.update']);
		Permission::firstOrCreate(['name' => 'client.destroy']);

		Permission::firstOrCreate(['name' => 'list60.index']);
		Permission::firstOrCreate(['name' => 'list60.list']);
		Permission::firstOrCreate(['name' => 'list60.create']);
		Permission::firstOrCreate(['name' => 'list60.show']);
		Permission::firstOrCreate(['name' => 'list60.edit']);
		Permission::firstOrCreate(['name' => 'list60.store']);
		Permission::firstOrCreate(['name' => 'list60.update']);
		Permission::firstOrCreate(['name' => 'list60.destroy']);

		Permission::firstOrCreate(['name' => 'task.index']);
		Permission::firstOrCreate(['name' => 'task.list']);
		Permission::firstOrCreate(['name' => 'task.create']);
		Permission::firstOrCreate(['name' => 'task.show']);
		Permission::firstOrCreate(['name' => 'task.edit']);
		Permission::firstOrCreate(['name' => 'task.store']);
		Permission::firstOrCreate(['name' => 'task.update']);
		Permission::firstOrCreate(['name' => 'task.destroy']);

		Permission::firstOrCreate(['name' => 'time.index']);
		Permission::firstOrCreate(['name' => 'time.list']);
		Permission::firstOrCreate(['name' => 'time.create']);
		Permission::firstOrCreate(['name' => 'time.show']);
		Permission::firstOrCreate(['name' => 'time.edit']);
		Permission::firstOrCreate(['name' => 'time.store']);
		Permission::firstOrCreate(['name' => 'time.update']);
		Permission::firstOrCreate(['name' => 'time.destroy']);

		Permission::firstOrCreate(['name' => 'documentation.index']);
		Permission::firstOrCreate(['name' => 'documentation.list']);
		Permission::firstOrCreate(['name' => 'documentation.create']);
		Permission::firstOrCreate(['name' => 'documentation.show']);
		Permission::firstOrCreate(['name' => 'documentation.edit']);
		Permission::firstOrCreate(['name' => 'documentation.store']);
		Permission::firstOrCreate(['name' => 'documentation.update']);
		Permission::firstOrCreate(['name' => 'documentation.destroy']);

		Permission::firstOrCreate(['name' => 'earning.index']);
		Permission::firstOrCreate(['name' => 'earning.list']);
		Permission::firstOrCreate(['name' => 'earning.create']);
		Permission::firstOrCreate(['name' => 'earning.show']);
		Permission::firstOrCreate(['name' => 'earning.edit']);
		Permission::firstOrCreate(['name' => 'earning.store']);
		Permission::firstOrCreate(['name' => 'earning.update']);
		Permission::firstOrCreate(['name' => 'earning.destroy']);

		Permission::firstOrCreate(['name' => 'expense.index']);
		Permission::firstOrCreate(['name' => 'expense.list']);
		Permission::firstOrCreate(['name' => 'expense.create']);
		Permission::firstOrCreate(['name' => 'expense.show']);
		Permission::firstOrCreate(['name' => 'expense.edit']);
		Permission::firstOrCreate(['name' => 'expense.store']);
		Permission::firstOrCreate(['name' => 'expense.update']);
		Permission::firstOrCreate(['name' => 'expense.destroy']);

		Permission::firstOrCreate(['name' => 'accounting.index']);
		Permission::firstOrCreate(['name' => 'accounting.list']);
		Permission::firstOrCreate(['name' => 'accounting.create']);
		Permission::firstOrCreate(['name' => 'accounting.show']);
		Permission::firstOrCreate(['name' => 'accounting.edit']);
		Permission::firstOrCreate(['name' => 'accounting.store']);
		Permission::firstOrCreate(['name' => 'accounting.update']);
		Permission::firstOrCreate(['name' => 'accounting.destroy']);

		Permission::firstOrCreate(['name' => 'financial.index']);
		Permission::firstOrCreate(['name' => 'financial.list']);
		Permission::firstOrCreate(['name' => 'financial.create']);
		Permission::firstOrCreate(['name' => 'financial.show']);
		Permission::firstOrCreate(['name' => 'financial.edit']);
		Permission::firstOrCreate(['name' => 'financial.store']);
		Permission::firstOrCreate(['name' => 'financial.update']);
		Permission::firstOrCreate(['name' => 'financial.destroy']);

		Permission::firstOrCreate(['name' => 'department.index']);
		Permission::firstOrCreate(['name' => 'department.list']);
		Permission::firstOrCreate(['name' => 'department.create']);
		Permission::firstOrCreate(['name' => 'department.show']);
		Permission::firstOrCreate(['name' => 'department.edit']);
		Permission::firstOrCreate(['name' => 'department.store']);
		Permission::firstOrCreate(['name' => 'department.update']);
		Permission::firstOrCreate(['name' => 'department.destroy']);

		Permission::firstOrCreate(['name' => 'funnel.index']);
		Permission::firstOrCreate(['name' => 'funnel.list']);
		Permission::firstOrCreate(['name' => 'funnel.create']);
		Permission::firstOrCreate(['name' => 'funnel.show']);
		Permission::firstOrCreate(['name' => 'funnel.edit']);
		Permission::firstOrCreate(['name' => 'funnel.store']);
		Permission::firstOrCreate(['name' => 'funnel.update']);
		Permission::firstOrCreate(['name' => 'funnel.destroy']);

		Permission::firstOrCreate(['name' => 'automation.index']);
		Permission::firstOrCreate(['name' => 'automation.list']);
		Permission::firstOrCreate(['name' => 'automation.create']);
		Permission::firstOrCreate(['name' => 'automation.show']);
		Permission::firstOrCreate(['name' => 'automation.edit']);
		Permission::firstOrCreate(['name' => 'automation.store']);
		Permission::firstOrCreate(['name' => 'automation.update']);
		Permission::firstOrCreate(['name' => 'automation.destroy']);

		Permission::firstOrCreate(['name' => 'integration.index']);
		Permission::firstOrCreate(['name' => 'integration.list']);
		Permission::firstOrCreate(['name' => 'integration.create']);
		Permission::firstOrCreate(['name' => 'integration.show']);
		Permission::firstOrCreate(['name' => 'integration.edit']);
		Permission::firstOrCreate(['name' => 'integration.store']);
		Permission::firstOrCreate(['name' => 'integration.update']);
		Permission::firstOrCreate(['name' => 'integration.destroy']);

		Permission::firstOrCreate(['name' => 'invoice.index']);
		Permission::firstOrCreate(['name' => 'invoice.list']);
		Permission::firstOrCreate(['name' => 'invoice.create']);
		Permission::firstOrCreate(['name' => 'invoice.show']);
		Permission::firstOrCreate(['name' => 'invoice.edit']);
		Permission::firstOrCreate(['name' => 'invoice.store']);
		Permission::firstOrCreate(['name' => 'invoice.update']);
		Permission::firstOrCreate(['name' => 'invoice.destroy']);

		Permission::firstOrCreate(['name' => 'payment.index']);
		Permission::firstOrCreate(['name' => 'payment.list']);
		Permission::firstOrCreate(['name' => 'payment.create']);
		Permission::firstOrCreate(['name' => 'payment.show']);
		Permission::firstOrCreate(['name' => 'payment.edit']);
		Permission::firstOrCreate(['name' => 'payment.store']);
		Permission::firstOrCreate(['name' => 'payment.update']);
		Permission::firstOrCreate(['name' => 'payment.destroy']);

		Permission::firstOrCreate(['name' => 'campaign.index']);
		Permission::firstOrCreate(['name' => 'campaign.list']);
		Permission::firstOrCreate(['name' => 'campaign.create']);
		Permission::firstOrCreate(['name' => 'campaign.show']);
		Permission::firstOrCreate(['name' => 'campaign.edit']);
		Permission::firstOrCreate(['name' => 'campaign.store']);
		Permission::firstOrCreate(['name' => 'campaign.update']);
		Permission::firstOrCreate(['name' => 'campaign.destroy']);

		Permission::firstOrCreate(['name' => 'project.index']);
		Permission::firstOrCreate(['name' => 'project.list']);
		Permission::firstOrCreate(['name' => 'project.create']);
		Permission::firstOrCreate(['name' => 'project.show']);
		Permission::firstOrCreate(['name' => 'project.edit']);
		Permission::firstOrCreate(['name' => 'project.store']);
		Permission::firstOrCreate(['name' => 'project.update']);
		Permission::firstOrCreate(['name' => 'project.destroy']);
		Permission::firstOrCreate(['name' => 'project.tasks']);
		Permission::firstOrCreate(['name' => 'project.calendar']);

		Permission::firstOrCreate(['name' => 'pages.edit']);

		Permission::firstOrCreate(['name' => 'category.list']);
		Permission::firstOrCreate(['name' => 'message.list']);
		Permission::firstOrCreate(['name' => 'template.list']);

		Permission::firstOrCreate(['name' => 'hosting.index']);
		Permission::firstOrCreate(['name' => 'hosting.list']);
		Permission::firstOrCreate(['name' => 'hosting.create']);
		Permission::firstOrCreate(['name' => 'hosting.show']);
		Permission::firstOrCreate(['name' => 'hosting.edit']);
		Permission::firstOrCreate(['name' => 'hosting.store']);
		Permission::firstOrCreate(['name' => 'hosting.update']);
		Permission::firstOrCreate(['name' => 'hosting.destroy']);

		Permission::firstOrCreate(['name' => 'domain.index']);
		Permission::firstOrCreate(['name' => 'domain.list']);
		Permission::firstOrCreate(['name' => 'domain.create']);
		Permission::firstOrCreate(['name' => 'domain.show']);
		Permission::firstOrCreate(['name' => 'domain.edit']);
		Permission::firstOrCreate(['name' => 'domain.store']);
		Permission::firstOrCreate(['name' => 'domain.update']);
		Permission::firstOrCreate(['name' => 'domain.destroy']);

		Permission::firstOrCreate(['name' => 'server.index']);
		Permission::firstOrCreate(['name' => 'server.list']);
		Permission::firstOrCreate(['name' => 'server.create']);
		Permission::firstOrCreate(['name' => 'server.show']);
		Permission::firstOrCreate(['name' => 'server.edit']);
		Permission::firstOrCreate(['name' => 'server.store']);
		Permission::firstOrCreate(['name' => 'server.update']);
		Permission::firstOrCreate(['name' => 'server.destroy']);

		Permission::firstOrCreate(['name' => 'software.index']);
		Permission::firstOrCreate(['name' => 'software.list']);
		Permission::firstOrCreate(['name' => 'software.create']);
		Permission::firstOrCreate(['name' => 'software.show']);
		Permission::firstOrCreate(['name' => 'software.edit']);
		Permission::firstOrCreate(['name' => 'software.store']);
		Permission::firstOrCreate(['name' => 'software.update']);
		Permission::firstOrCreate(['name' => 'software.destroy']);

		Permission::firstOrCreate(['name' => 'certification.index']);
		Permission::firstOrCreate(['name' => 'certification.list']);
		Permission::firstOrCreate(['name' => 'certification.create']);
		Permission::firstOrCreate(['name' => 'certification.show']);
		Permission::firstOrCreate(['name' => 'certification.edit']);
		Permission::firstOrCreate(['name' => 'certification.store']);
		Permission::firstOrCreate(['name' => 'certification.update']);
		Permission::firstOrCreate(['name' => 'certification.destroy']);

		Permission::firstOrCreate(['name' => 'stylebook.index']);
		Permission::firstOrCreate(['name' => 'stylebook.list']);
		Permission::firstOrCreate(['name' => 'stylebook.create']);
		Permission::firstOrCreate(['name' => 'stylebook.show']);
		Permission::firstOrCreate(['name' => 'stylebook.edit']);
		Permission::firstOrCreate(['name' => 'stylebook.store']);
		Permission::firstOrCreate(['name' => 'stylebook.update']);
		Permission::firstOrCreate(['name' => 'stylebook.destroy']);

		// E-commerce permissions
		Permission::firstOrCreate(['name' => 'product.index']);
		Permission::firstOrCreate(['name' => 'product.list']);
		Permission::firstOrCreate(['name' => 'product.create']);
		Permission::firstOrCreate(['name' => 'product.show']);
		Permission::firstOrCreate(['name' => 'product.edit']);
		Permission::firstOrCreate(['name' => 'product.store']);
		Permission::firstOrCreate(['name' => 'product.update']);
		Permission::firstOrCreate(['name' => 'product.destroy']);

		Permission::firstOrCreate(['name' => 'order.index']);
		Permission::firstOrCreate(['name' => 'order.list']);
		Permission::firstOrCreate(['name' => 'order.create']);
		Permission::firstOrCreate(['name' => 'order.show']);
		Permission::firstOrCreate(['name' => 'order.edit']);
		Permission::firstOrCreate(['name' => 'order.store']);
		Permission::firstOrCreate(['name' => 'order.update']);
		Permission::firstOrCreate(['name' => 'order.destroy']);

		Permission::firstOrCreate(['name' => 'ecommerce.index']);
		Permission::firstOrCreate(['name' => 'ecommerce.list']);
		Permission::firstOrCreate(['name' => 'ecommerce.create']);
		Permission::firstOrCreate(['name' => 'ecommerce.show']);
		Permission::firstOrCreate(['name' => 'ecommerce.edit']);
		Permission::firstOrCreate(['name' => 'ecommerce.store']);
		Permission::firstOrCreate(['name' => 'ecommerce.update']);
		Permission::firstOrCreate(['name' => 'ecommerce.destroy']);
		Permission::firstOrCreate(['name' => 'ecommerce.dashboard']);
		Permission::firstOrCreate(['name' => 'ecommerce.settings']);

		// Academy permissions
		Permission::firstOrCreate(['name' => 'academy.index']);
		Permission::firstOrCreate(['name' => 'academy.list']);
		Permission::firstOrCreate(['name' => 'academy.create']);
		Permission::firstOrCreate(['name' => 'academy.show']);
		Permission::firstOrCreate(['name' => 'academy.edit']);
		Permission::firstOrCreate(['name' => 'academy.store']);
		Permission::firstOrCreate(['name' => 'academy.update']);
		Permission::firstOrCreate(['name' => 'academy.destroy']);

		$rootRole = Role::firstOrCreate(['name' => 'root']);
		$rootRole->syncPermissions([
			'user.management',
		]);

		$administratorRole = Role::firstOrCreate(['name' => 'admin']);
		$administratorRole->syncPermissions([
			'user.index',
			'user.list',
			'user.create',
			'user.show',
			'user.edit',
			'user.store',
			'user.update',
			'user.destroy',
			'client.index',
			'client.list',
			'client.create',
			'client.show',
			'client.edit',
			'client.store',
			'client.update',
			'client.destroy',
			'contact.index',
			'contact.list',
			'contact.create',
			'contact.show',
			'contact.edit',
			'contact.store',
			'contact.update',
			'contact.destroy',
			'collaborator.index',
			'collaborator.list',
			'collaborator.create',
			'collaborator.show',
			'collaborator.edit',
			'collaborator.store',
			'collaborator.update',
			'collaborator.destroy',
			'list60.list',
			'mail.index',
			'mail.list',
			'mail.create',
			'mail.show',
			'mail.edit',
			'mail.store',
			'mail.update',
			'mail.destroy',
			'project.index',
			'project.list',
			'project.create',
			'project.show',
			'project.edit',
			'project.store',
			'project.update',
			'project.destroy',
			'project.tasks',
			'project.calendar',
			'service.index',
			'service.list',
			'service.create',
			'service.show',
			'service.edit',
			'service.store',
			'service.update',
			'service.destroy',
			'pages.edit',
			'hosting.index',
			'hosting.list',
			'hosting.create',
			'hosting.show',
			'hosting.edit',
			'hosting.store',
			'hosting.update',
			'category.list',
			'message.list',
			'template.list',
			'domain.index',
			'domain.list',
			'domain.create',
			'domain.show',
			'domain.edit',
			'domain.store',
			'domain.update',
			'domain.destroy',
			'server.index',
			'server.list',
			'server.create',
			'server.show',
			'server.edit',
			'server.store',
			'server.update',
			'server.destroy',
			'software.index',
			'software.list',
			'software.create',
			'software.show',
			'software.edit',
			'software.store',
			'software.update',
			'software.destroy',
			'certification.index',
			'certification.list',
			'certification.create',
			'certification.show',
			'certification.edit',
			'certification.store',
			'certification.update',
			'certification.destroy',
			'stylebook.index',
			'stylebook.list',
			'stylebook.create',
			'stylebook.show',
			'stylebook.edit',
			'stylebook.store',
			'stylebook.update',
			'stylebook.destroy',
			'invoice.index',
			'invoice.list',
			'invoice.create',
			'invoice.show',
			'invoice.edit',
			'invoice.store',
			'invoice.update',
			'invoice.destroy',
			'payment.index',
			'payment.list',
			'payment.create',
			'payment.show',
			'payment.edit',
			'payment.store',
			'payment.update',
			'payment.destroy',
			'academy.index',
			'academy.list',
			'academy.create',
			'academy.show',
			'academy.edit',
			'academy.store',
			'academy.update',
			'academy.destroy',
		]);

		$collaboratorRole = Role::firstOrCreate(['name' => 'collaborator']);
		$collaboratorRole->syncPermissions([
			'user.list',
			'today.list',
			'chat.list',
			'mail.list',
			'service.list',
			'client.list',
			'list60.list',
			'task.list',
			'time.list',
			'documentation.list',
			'earning.list',
			'expense.list',
			'accounting.list',
			'financial.list',
			'department.list',
			'funnel.list',
			'automation.list',
			'integration.list',
			'campaign.list',
			'academy.list',
			'profile.show',
			'profile.edit',
			'profile.update',
			// E-commerce permissions (admin only)
			'product.index',
			'product.list',
			'product.create',
			'product.show',
			'product.edit',
			'product.store',
			'product.update',
			'product.destroy',
			'order.index',
			'order.list',
			'order.create',
			'order.show',
			'order.edit',
			'order.store',
			'order.update',
			'order.destroy',
			'ecommerce.index',
			'ecommerce.list',
			'ecommerce.create',
			'ecommerce.show',
			'ecommerce.edit',
			'ecommerce.store',
			'ecommerce.update',
			'ecommerce.destroy',
			'ecommerce.dashboard',
			'ecommerce.settings',
		]);

		$editorRole = Role::firstOrCreate(['name' => 'editor']);
		$editorRole->syncPermissions([
			'profile.show',
			'profile.edit',
			'profile.update',
			'password.update',
			'project.index',
			'project.list',
			'service.index',
			'service.list',
			'service.show',
			'pages.edit',
			'hosting.index',
			'hosting.list',
			'hosting.create',
			'hosting.show',
			'hosting.edit',
			'hosting.store',
			'hosting.update',
			'category.list',
			'message.list',
			'template.list',
		]);

		$auditorRole = Role::firstOrCreate(['name' => 'auditor']);
		$auditorRole->syncPermissions([
			'profile.show',
			'profile.edit',
			'profile.update',
			'password.update',
		]);

		$technicalRole = Role::firstOrCreate(['name' => 'technical']);
		$technicalRole->syncPermissions([
			'profile.show',
			'profile.edit',
			'profile.update',
			'password.update',
		]);

		$clientRole = Role::firstOrCreate(['name' => 'client']);
		$clientRole->syncPermissions([
			'profile.show',
			'profile.edit',
			'profile.update',
			'password.update',
			'service.index',
			'service.list',
			'service.show',
			'invoice.index',
			'invoice.list',
			'invoice.show',
			'payment.index',
			'payment.list',
			'payment.show',
			'project.index',
			'project.list',
			'project.show',
		]);

		$userRole = Role::firstOrCreate(['name' => 'user']);
		$userRole->syncPermissions([
			'profile.show',
			'profile.edit',
			'profile.update',
			'password.update',
		]);

		$guestRole = Role::firstOrCreate(['name' => 'guest']);
		$guestRole->syncPermissions([
			'profile.show',
			'profile.edit',
			'profile.update',
			'password.update',
		]);

		$developerRole = Role::firstOrCreate(['name' => 'developer']);
		$developerRole->syncPermissions([
			'user.list',
			'user.create',
			'user.show',
			'user.edit',
			'user.store',
			'user.update',
			'user.destroy',
			'today.list',
			'today.create',
			'today.show',
			'today.edit',
			'today.store',
			'today.update',
			'today.destroy',
			'chat.index',
			'chat.list',
			'chat.create',
			'chat.show',
			'chat.edit',
			'chat.store',
			'chat.update',
			'chat.destroy',
			'mail.list',
			'service.index',
			'service.list',
			'service.create',
			'service.show',
			'service.edit',
			'service.store',
			'service.update',
			'service.destroy',
			'contact.index',
			'contact.list',
			'contact.create',
			'contact.show',
			'contact.edit',
			'contact.store',
			'contact.update',
			'contact.destroy',
			'client.index',
			'client.list',
			'client.create',
			'client.show',
			'client.edit',
			'client.store',
			'client.update',
			'client.destroy',
			'list60.index',
			'list60.list',
			'list60.create',
			'list60.show',
			'list60.edit',
			'list60.store',
			'list60.update',
			'list60.destroy',
			'task.index',
			'task.list',
			'task.create',
			'task.show',
			'task.edit',
			'task.store',
			'task.update',
			'task.destroy',
			'time.index',
			'time.list',
			'time.create',
			'time.show',
			'time.edit',
			'time.store',
			'time.update',
			'time.destroy',
			'documentation.index',
			'documentation.list',
			'documentation.create',
			'documentation.show',
			'documentation.edit',
			'documentation.store',
			'documentation.update',
			'documentation.destroy',
			'earning.index',
			'earning.list',
			'earning.create',
			'earning.show',
			'earning.edit',
			'earning.store',
			'earning.update',
			'earning.destroy',
			'expense.index',
			'expense.list',
			'expense.create',
			'expense.show',
			'expense.edit',
			'expense.store',
			'expense.update',
			'expense.destroy',
			'accounting.index',
			'accounting.list',
			'accounting.create',
			'accounting.show',
			'accounting.edit',
			'accounting.store',
			'accounting.update',
			'accounting.destroy',
			'financial.index',
			'financial.list',
			'financial.create',
			'financial.show',
			'financial.edit',
			'financial.store',
			'financial.update',
			'financial.destroy',
			'department.index',
			'department.list',
			'department.create',
			'department.show',
			'department.edit',
			'department.store',
			'department.update',
			'department.destroy',
			'funnel.index',
			'funnel.list',
			'funnel.create',
			'funnel.show',
			'funnel.edit',
			'funnel.store',
			'funnel.update',
			'funnel.destroy',
			'automation.index',
			'automation.list',
			'automation.create',
			'automation.show',
			'automation.edit',
			'automation.store',
			'automation.update',
			'automation.destroy',
			'integration.index',
			'integration.list',
			'integration.create',
			'integration.show',
			'integration.edit',
			'integration.store',
			'integration.update',
			'integration.destroy',
			'campaign.index',
			'campaign.list',
			'campaign.create',
			'campaign.show',
			'campaign.edit',
			'campaign.store',
			'campaign.update',
			'campaign.destroy',
			'hosting.index',
			'hosting.list',
			'hosting.create',
			'hosting.show',
			'hosting.edit',
			'hosting.store',
			'domain.index',
			'domain.list',
			'domain.create',
			'domain.show',
			'domain.edit',
			'domain.store',
			'domain.update',
			'domain.destroy',
			'server.index',
			'server.list',
			'server.create',
			'server.show',
			'server.edit',
			'server.store',
			'server.update',
			'server.destroy',
			'software.index',
			'software.list',
			'software.create',
			'software.show',
			'software.edit',
			'software.store',
			'software.update',
			'software.destroy',
			'certification.index',
			'certification.list',
			'certification.create',
			'certification.show',
			'certification.edit',
			'certification.store',
			'certification.update',
			'certification.destroy',
			'stylebook.index',
			'stylebook.list',
			'stylebook.create',
			'stylebook.show',
			'stylebook.edit',
			'stylebook.store',
			'stylebook.update',
			'stylebook.destroy',
			'academy.index',
			'academy.list',
			'academy.create',
			'academy.show',
			'academy.edit',
			'academy.store',
			'academy.update',
			'academy.destroy',
		]);
	}
}
