<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class InvoicePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
         * ==================================================================================
         * PERMISSIONS ARE NO LONGER USED - AUTHORIZATION NOW BASED ON ROLES + POLICIES
         * ==================================================================================
         *
         * This seeder has been disabled. Authorization is now handled by:
         * - Spatie Roles (admin, collaborator, client, etc.)
         * - Laravel Policies (InvoicePolicy, etc.)
         *
         * See RolesAndPermissionsSeeder for more information.
         * ==================================================================================
         */

        $this->command->info('⏭️  Skipping invoice permissions (role-based authorization active)');

        /*
        // Create invoice permissions if they don't exist
        $invoicePermissions = [
            'invoice.index',
            'invoice.list',
            'invoice.create',
            'invoice.show',
            'invoice.edit',
            'invoice.store',
            'invoice.update',
            'invoice.destroy',
        ];

        foreach ($invoicePermissions as $permission)
        {
            // Permission::firstOrCreate(['name' => $permission]);
        }

        // Update admin role to include invoice permissions
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole)
        {
            // $adminRole->givePermissionTo($invoicePermissions);
        }

        // Update client role to include view permissions for invoices
        $clientRole = Role::where('name', 'client')->first();
        if ($clientRole)
        {
            // $clientRole->givePermissionTo([
            //     'invoice.index',
            //     'invoice.list',
            //     'invoice.show',
            // ]);
        }

        // Update client role to include view permissions for services and projects
        if ($clientRole)
        {
            // $clientRole->givePermissionTo([
            //     'service.index',
            //     'service.list',
            //     'service.show',
            //     'project.index',
            //     'project.list',
            //     'project.show',
            //     'contact.index',
            //     'contact.list',
            //     'contact.show',
            // ]);
        }

        $this->command->info('✅ Invoice permissions and client role updated successfully!');
        */
    }
}
