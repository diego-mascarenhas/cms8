<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
	public function run()
	{
		Permission::create(['name' => 'user.management']);
		Permission::create(['name' => 'profile.show']);
		Permission::create(['name' => 'profile.edit']);
		Permission::create(['name' => 'profile.update']);
		Permission::create(['name' => 'password.update']);

		Permission::create(['name' => 'user.index']);
		Permission::create(['name' => 'user.list']);
		Permission::create(['name' => 'user.create']);
		Permission::create(['name' => 'user.show']);
		Permission::create(['name' => 'user.edit']);
		Permission::create(['name' => 'user.store']);
		Permission::create(['name' => 'user.update']);
		Permission::create(['name' => 'user.destroy']);

		Permission::create(['name' => 'today.index']);
		Permission::create(['name' => 'today.list']);
		Permission::create(['name' => 'today.create']);
		Permission::create(['name' => 'today.show']);
		Permission::create(['name' => 'today.edit']);
		Permission::create(['name' => 'today.store']);
		Permission::create(['name' => 'today.update']);
		Permission::create(['name' => 'today.destroy']);

		Permission::create(['name' => 'chat.index']);
		Permission::create(['name' => 'chat.list']);
		Permission::create(['name' => 'chat.create']);
		Permission::create(['name' => 'chat.show']);
		Permission::create(['name' => 'chat.edit']);
		Permission::create(['name' => 'chat.store']);
		Permission::create(['name' => 'chat.update']);
		Permission::create(['name' => 'chat.destroy']);

		Permission::create(['name' => 'mail.index']);
		Permission::create(['name' => 'mail.list']);
		Permission::create(['name' => 'mail.create']);
		Permission::create(['name' => 'mail.show']);
		Permission::create(['name' => 'mail.edit']);
		Permission::create(['name' => 'mail.store']);
		Permission::create(['name' => 'mail.update']);
		Permission::create(['name' => 'mail.destroy']);

		Permission::create(['name' => 'service.index']);
		Permission::create(['name' => 'service.list']);
		Permission::create(['name' => 'service.create']);
		Permission::create(['name' => 'service.show']);
		Permission::create(['name' => 'service.edit']);
		Permission::create(['name' => 'service.store']);
		Permission::create(['name' => 'service.update']);
		Permission::create(['name' => 'service.destroy']);

		Permission::create(['name' => 'contact.index']);
		Permission::create(['name' => 'contact.list']);
		Permission::create(['name' => 'contact.create']);
		Permission::create(['name' => 'contact.show']);
		Permission::create(['name' => 'contact.edit']);
		Permission::create(['name' => 'contact.store']);
		Permission::create(['name' => 'contact.update']);
		Permission::create(['name' => 'contact.destroy']);

		Permission::create(['name' => 'collaborator.index']);
		Permission::create(['name' => 'collaborator.list']);
		Permission::create(['name' => 'collaborator.create']);
		Permission::create(['name' => 'collaborator.show']);
		Permission::create(['name' => 'collaborator.edit']);
		Permission::create(['name' => 'collaborator.store']);
		Permission::create(['name' => 'collaborator.update']);
		Permission::create(['name' => 'collaborator.destroy']);

		Permission::create(['name' => 'client.index']);
		Permission::create(['name' => 'client.list']);
		Permission::create(['name' => 'client.create']);
		Permission::create(['name' => 'client.show']);
		Permission::create(['name' => 'client.edit']);
		Permission::create(['name' => 'client.store']);
		Permission::create(['name' => 'client.update']);
		Permission::create(['name' => 'client.destroy']);

		Permission::create(['name' => 'list60.index']);
		Permission::create(['name' => 'list60.list']);
		Permission::create(['name' => 'list60.create']);
		Permission::create(['name' => 'list60.show']);
		Permission::create(['name' => 'list60.edit']);
		Permission::create(['name' => 'list60.store']);
		Permission::create(['name' => 'list60.update']);
		Permission::create(['name' => 'list60.destroy']);

		Permission::create(['name' => 'task.index']);
		Permission::create(['name' => 'task.list']);
		Permission::create(['name' => 'task.create']);
		Permission::create(['name' => 'task.show']);
		Permission::create(['name' => 'task.edit']);
		Permission::create(['name' => 'task.store']);
		Permission::create(['name' => 'task.update']);
		Permission::create(['name' => 'task.destroy']);

		Permission::create(['name' => 'time.index']);
		Permission::create(['name' => 'time.list']);
		Permission::create(['name' => 'time.create']);
		Permission::create(['name' => 'time.show']);
		Permission::create(['name' => 'time.edit']);
		Permission::create(['name' => 'time.store']);
		Permission::create(['name' => 'time.update']);
		Permission::create(['name' => 'time.destroy']);

		Permission::create(['name' => 'documentation.index']);
		Permission::create(['name' => 'documentation.list']);
		Permission::create(['name' => 'documentation.create']);
		Permission::create(['name' => 'documentation.show']);
		Permission::create(['name' => 'documentation.edit']);
		Permission::create(['name' => 'documentation.store']);
		Permission::create(['name' => 'documentation.update']);
		Permission::create(['name' => 'documentation.destroy']);

		Permission::create(['name' => 'earning.index']);
		Permission::create(['name' => 'earning.list']);
		Permission::create(['name' => 'earning.create']);
		Permission::create(['name' => 'earning.show']);
		Permission::create(['name' => 'earning.edit']);
		Permission::create(['name' => 'earning.store']);
		Permission::create(['name' => 'earning.update']);
		Permission::create(['name' => 'earning.destroy']);

		Permission::create(['name' => 'expense.index']);
		Permission::create(['name' => 'expense.list']);
		Permission::create(['name' => 'expense.create']);
		Permission::create(['name' => 'expense.show']);
		Permission::create(['name' => 'expense.edit']);
		Permission::create(['name' => 'expense.store']);
		Permission::create(['name' => 'expense.update']);
		Permission::create(['name' => 'expense.destroy']);

		Permission::create(['name' => 'accounting.index']);
		Permission::create(['name' => 'accounting.list']);
		Permission::create(['name' => 'accounting.create']);
		Permission::create(['name' => 'accounting.show']);
		Permission::create(['name' => 'accounting.edit']);
		Permission::create(['name' => 'accounting.store']);
		Permission::create(['name' => 'accounting.update']);
		Permission::create(['name' => 'accounting.destroy']);

		Permission::create(['name' => 'financial.index']);
		Permission::create(['name' => 'financial.list']);
		Permission::create(['name' => 'financial.create']);
		Permission::create(['name' => 'financial.show']);
		Permission::create(['name' => 'financial.edit']);
		Permission::create(['name' => 'financial.store']);
		Permission::create(['name' => 'financial.update']);
		Permission::create(['name' => 'financial.destroy']);

		Permission::create(['name' => 'department.index']);
		Permission::create(['name' => 'department.list']);
		Permission::create(['name' => 'department.create']);
		Permission::create(['name' => 'department.show']);
		Permission::create(['name' => 'department.edit']);
		Permission::create(['name' => 'department.store']);
		Permission::create(['name' => 'department.update']);
		Permission::create(['name' => 'department.destroy']);

		Permission::create(['name' => 'funnel.index']);
		Permission::create(['name' => 'funnel.list']);
		Permission::create(['name' => 'funnel.create']);
		Permission::create(['name' => 'funnel.show']);
		Permission::create(['name' => 'funnel.edit']);
		Permission::create(['name' => 'funnel.store']);
		Permission::create(['name' => 'funnel.update']);
		Permission::create(['name' => 'funnel.destroy']);

		Permission::create(['name' => 'automation.index']);
		Permission::create(['name' => 'automation.list']);
		Permission::create(['name' => 'automation.create']);
		Permission::create(['name' => 'automation.show']);
		Permission::create(['name' => 'automation.edit']);
		Permission::create(['name' => 'automation.store']);
		Permission::create(['name' => 'automation.update']);
		Permission::create(['name' => 'automation.destroy']);

		Permission::create(['name' => 'integration.index']);
		Permission::create(['name' => 'integration.list']);
		Permission::create(['name' => 'integration.create']);
		Permission::create(['name' => 'integration.show']);
		Permission::create(['name' => 'integration.edit']);
		Permission::create(['name' => 'integration.store']);
		Permission::create(['name' => 'integration.update']);
		Permission::create(['name' => 'integration.destroy']);

		Permission::create(['name' => 'invoice.index']);
		Permission::create(['name' => 'invoice.list']);
		Permission::create(['name' => 'invoice.create']);
		Permission::create(['name' => 'invoice.show']);
		Permission::create(['name' => 'invoice.edit']);
		Permission::create(['name' => 'invoice.store']);
		Permission::create(['name' => 'invoice.update']);
		Permission::create(['name' => 'invoice.destroy']);

		Permission::create(['name' => 'campaign.index']);
		Permission::create(['name' => 'campaign.list']);
		Permission::create(['name' => 'campaign.create']);
		Permission::create(['name' => 'campaign.show']);
		Permission::create(['name' => 'campaign.edit']);
		Permission::create(['name' => 'campaign.store']);
		Permission::create(['name' => 'campaign.update']);
		Permission::create(['name' => 'campaign.destroy']);

		Permission::create(['name' => 'project.index']);
		Permission::create(['name' => 'project.list']);
		Permission::create(['name' => 'project.create']);
		Permission::create(['name' => 'project.show']);
		Permission::create(['name' => 'project.edit']);
		Permission::create(['name' => 'project.store']);
		Permission::create(['name' => 'project.update']);
		Permission::create(['name' => 'project.destroy']);
		Permission::create(['name' => 'project.tasks']);
		Permission::create(['name' => 'project.calendar']);

		Permission::create(['name' => 'pages.edit']);

		Permission::create(['name' => 'category.list']);
		Permission::create(['name' => 'message.list']);
		Permission::create(['name' => 'template.list']);

		Permission::create(['name' => 'hosting.index']);
		Permission::create(['name' => 'hosting.list']);
		Permission::create(['name' => 'hosting.create']);
		Permission::create(['name' => 'hosting.show']);
		Permission::create(['name' => 'hosting.edit']);
		Permission::create(['name' => 'hosting.store']);
		Permission::create(['name' => 'hosting.update']);
		Permission::create(['name' => 'hosting.destroy']);

		Permission::create(['name' => 'domain.index']);
		Permission::create(['name' => 'domain.list']);
		Permission::create(['name' => 'domain.create']);
		Permission::create(['name' => 'domain.show']);
		Permission::create(['name' => 'domain.edit']);
		Permission::create(['name' => 'domain.store']);
		Permission::create(['name' => 'domain.update']);
		Permission::create(['name' => 'domain.destroy']);

		Permission::create(['name' => 'server.index']);
		Permission::create(['name' => 'server.list']);
		Permission::create(['name' => 'server.create']);
		Permission::create(['name' => 'server.show']);
		Permission::create(['name' => 'server.edit']);
		Permission::create(['name' => 'server.store']);
		Permission::create(['name' => 'server.update']);
		Permission::create(['name' => 'server.destroy']);

		Permission::create(['name' => 'software.index']);
		Permission::create(['name' => 'software.list']);
		Permission::create(['name' => 'software.create']);
		Permission::create(['name' => 'software.show']);
		Permission::create(['name' => 'software.edit']);
		Permission::create(['name' => 'software.store']);
		Permission::create(['name' => 'software.update']);
		Permission::create(['name' => 'software.destroy']);

		Permission::create(['name' => 'certification.index']);
		Permission::create(['name' => 'certification.list']);
		Permission::create(['name' => 'certification.create']);
		Permission::create(['name' => 'certification.show']);
		Permission::create(['name' => 'certification.edit']);
		Permission::create(['name' => 'certification.store']);
		Permission::create(['name' => 'certification.update']);
		Permission::create(['name' => 'certification.destroy']);

		Permission::create(['name' => 'stylebook.index']);
		Permission::create(['name' => 'stylebook.list']);
		Permission::create(['name' => 'stylebook.create']);
		Permission::create(['name' => 'stylebook.show']);
		Permission::create(['name' => 'stylebook.edit']);
		Permission::create(['name' => 'stylebook.store']);
		Permission::create(['name' => 'stylebook.update']);
		Permission::create(['name' => 'stylebook.destroy']);

		$rootRole = Role::create(['name' => 'root']);
		$rootRole->syncPermissions([
			'user.management',
		]);

		$administratorRole = Role::create(['name' => 'admin']);
		$administratorRole->syncPermissions([
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
		]);

		$collaboratorRole = Role::create(['name' => 'collaborator']);
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
			'profile.show',
			'profile.edit',
			'profile.update',
		]);

		$editorRole = Role::create(['name' => 'editor']);
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

		$editorRole = Role::create(['name' => 'auditor']);
		$editorRole->syncPermissions([
			'profile.show',
			'profile.edit',
			'profile.update',
			'password.update',
		]);

		$technicalRole = Role::create(['name' => 'technical']);
		$technicalRole->syncPermissions([
			'profile.show',
			'profile.edit',
			'profile.update',
			'password.update',
		]);

		$clientRole = Role::create(['name' => 'client']);
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
			'project.index',
			'project.list',
			'project.show',
		]);

		$userRole = Role::create(['name' => 'user']);
		$userRole->syncPermissions([
			'profile.show',
			'profile.edit',
			'profile.update',
			'password.update',
		]);

		$userRole = Role::create(['name' => 'guest']);
		$userRole->syncPermissions([
			'profile.show',
			'profile.edit',
			'profile.update',
			'password.update',
		]);

		$userRole = Role::create(['name' => 'developer']);
		$userRole->syncPermissions([
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
		]);
	}
}
