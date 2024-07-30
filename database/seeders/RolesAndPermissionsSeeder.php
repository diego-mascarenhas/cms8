<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        Permission::create(['name' => 'user.management']);

        Permission::create(['name' => 'user.list']);
        Permission::create(['name' => 'user.create']);
        Permission::create(['name' => 'user.show']);
        Permission::create(['name' => 'user.edit']);
        Permission::create(['name' => 'user.store']);
        Permission::create(['name' => 'user.update']);
        Permission::create(['name' => 'user.destroy']);

        Permission::create(['name' => 'client.list']);
        Permission::create(['name' => 'client.create']);
        Permission::create(['name' => 'client.show']);
        Permission::create(['name' => 'client.edit']);
        Permission::create(['name' => 'client.store']);
        Permission::create(['name' => 'client.update']);
        Permission::create(['name' => 'client.destroy']);

        Permission::create(['name' => 'supplier.list']);
        Permission::create(['name' => 'supplier.create']);
        Permission::create(['name' => 'supplier.show']);
        Permission::create(['name' => 'supplier.edit']);
        Permission::create(['name' => 'supplier.store']);
        Permission::create(['name' => 'supplier.update']);
        Permission::create(['name' => 'supplier.destroy']);

        Permission::create(['name' => 'service.list']);
        Permission::create(['name' => 'service.create']);
        Permission::create(['name' => 'service.show']);
        Permission::create(['name' => 'service.edit']);
        Permission::create(['name' => 'service.store']);
        Permission::create(['name' => 'service.update']);
        Permission::create(['name' => 'service.destroy']);

        Permission::create(['name' => 'project.list']);
        Permission::create(['name' => 'project.create']);
        Permission::create(['name' => 'project.show']);
        Permission::create(['name' => 'project.edit']);
        Permission::create(['name' => 'project.store']);
        Permission::create(['name' => 'project.update']);
        Permission::create(['name' => 'project.destroy']);

        Permission::create(['name' => 'invoice.list']);
        Permission::create(['name' => 'invoice.create']);
        Permission::create(['name' => 'invoice.show']);
        Permission::create(['name' => 'invoice.edit']);
        Permission::create(['name' => 'invoice.store']);
        Permission::create(['name' => 'invoice.update']);
        Permission::create(['name' => 'invoice.destroy']);

        Permission::create(['name' => 'payment.list']);
        Permission::create(['name' => 'payment.create']);
        Permission::create(['name' => 'payment.show']);
        Permission::create(['name' => 'payment.edit']);
        Permission::create(['name' => 'payment.store']);
        Permission::create(['name' => 'payment.update']);
        Permission::create(['name' => 'payment.destroy']);

        Permission::create(['name' => 'communication.list']);
        Permission::create(['name' => 'communication.create']);
        Permission::create(['name' => 'communication.show']);
        Permission::create(['name' => 'communication.edit']);
        Permission::create(['name' => 'communication.store']);
        Permission::create(['name' => 'communication.update']);
        Permission::create(['name' => 'communication.destroy']);

        Permission::create(['name' => 'host.list']);
        Permission::create(['name' => 'host.create']);
        Permission::create(['name' => 'host.show']);
        Permission::create(['name' => 'host.edit']);
        Permission::create(['name' => 'host.store']);
        Permission::create(['name' => 'host.update']);
        Permission::create(['name' => 'host.destroy']);

        Permission::create(['name' => 'profile.show']);
        Permission::create(['name' => 'profile.edit']);
        Permission::create(['name' => 'profile.update']);
        Permission::create(['name' => 'password.update']);

        $rootRole = Role::create(['name' => 'root']);
        $rootRole->syncPermissions([
            'user.management',
            'user.list',
            'user.destroy',
            'client.destroy',
            'supplier.destroy',
            'service.destroy',
            'project.destroy',
            'invoice.destroy',
            'payment.destroy',
            'communication.destroy',
            'password.update',
        ]);
        
        $administratorRole = Role::create(['name' => 'admin']);
        $administratorRole->syncPermissions([
            'user.management',
            'user.list',
            'user.create',
            'user.show',
            'user.edit',
            'user.store',
            'user.update',
            'client.list',
            'client.create',
            'client.show',
            'client.edit',
            'client.store',
            'client.update',
            'supplier.list',
            'supplier.create',
            'supplier.show',
            'supplier.edit',
            'supplier.store',
            'supplier.update',
            'service.list',
            'service.create',
            'service.show',
            'service.edit',
            'service.store',
            'service.update',
            'project.list',
            'project.create',
            'project.show',
            'project.edit',
            'project.store',
            'project.update',
            'invoice.list',
            'invoice.create',
            'invoice.show',
            'invoice.store',
            'payment.list',
            'payment.create',
            'payment.show',
            'payment.edit',
            'payment.store',
            'payment.update',
            'communication.list',
            'communication.create',
            'communication.show',
            'communication.edit',
            'communication.store',
            'communication.update',
            'password.update',
        ]);

        $colaboratorRole = Role::create(['name' => 'colaborator']);
        $colaboratorRole->syncPermissions([
            'client.list',
            'client.create',
            'client.show',
            'client.edit',
            'client.store',
            'client.update',
            'service.list',
            'service.create',
            'service.show',
            'service.edit',
            'service.store',
            'service.update',
            'project.list',
            'project.create',
            'project.show',
            'project.edit',
            'project.store',
            'project.update',
            'communication.list',
            'password.update',
        ]);

        $editorRole = Role::create(['name' => 'editor']);
        $editorRole->syncPermissions([
            'profile.show',
            'profile.edit',
            'profile.update',
            'password.update',
        ]);

        $editorRole = Role::create(['name' => 'auditor']);
        $editorRole->syncPermissions([
            'client.list',
            'supplier.list',
            'invoice.list',
            'payment.list',
        ]);
        
        $clientRole = Role::create(['name' => 'client']);
        $clientRole->syncPermissions([
            'profile.show',
            'profile.edit',
            'profile.update',
            'password.update',
        ]);

        $technicalRole = Role::create(['name' => 'technical']);
        $technicalRole->syncPermissions([
            'host.list',
            'host.edit',
        ]);
        
        $guestRole = Role::create(['name' => 'guest']);
        $guestRole->syncPermissions([
            'profile.show',
            'profile.edit',
            'profile.update',
            'password.update',
        ]);
    }
}
