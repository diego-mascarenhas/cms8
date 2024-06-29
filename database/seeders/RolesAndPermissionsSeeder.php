<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        Permission::create(['name' => 'create clients']);
        Permission::create(['name' => 'edit clients']);
        Permission::create(['name' => 'edit own clients']);
        Permission::create(['name' => 'delete clients']);
        Permission::create(['name' => 'view information']);
        Permission::create(['name' => 'update profile']);
        Permission::create(['name' => 'change password']);

        $administratorRole = Role::create(['name' => 'Administrator']);
        $administratorRole->syncPermissions([
            'create clients',
            'edit clients',
            'delete clients',
        ]);

        $colaboratorRole = Role::create(['name' => 'Colaborator']);
        $colaboratorRole->syncPermissions([
            'create clients',
            'edit own clients',
        ]);

        $clientRole = Role::create(['name' => 'Client']);
        $clientRole->syncPermissions([
            'view information',
            'update profile',
            'change password',
        ]);
    }
}
