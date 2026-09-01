<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'Dashboard',
            'Create Client',
            'Client List',
            'Archive',
            'Events',
            'Delete Event',
            'Manage Users',
            'Activity Logs',
            'Send Transactions',
            'Event Records',
            'Events Records Duplicates',
            'View Archive Files',
            'Roles',
            'Permissions',
            'Import CSV',
            'Download Template',
            'Edit User Roles',
            'Update User Status',
            'Add Roles',
            'Delete Roles',
            'Transfer Selected',
            'Archive Clients',
            'Edit Client',
            'View Client',
            'Restore Archive',
            'View Removed Duplicates',
            'Reset Duplicate Review',
            'Download Archive',
            'Save Permissions',
            'Delete Permissions',
            'Add Permissions',
            'Undo Transfer',
            'Transfer Event',
            'Mark Not Duplicate',
            'Duplicate Review',
            'Preview Import',
        ];

        $roles = ['Super Admin', 'Admin', 'Staff', 'DSWD'];

        foreach ($permissions as $feature) {
            foreach ($roles as $role) {
                $allowed = in_array($role, ['Super Admin', 'Admin']);
                
                // Admin should not have Add Roles and Delete Roles
                if (in_array($feature, ['Add Roles', 'Delete Roles', 'Save Permissions', 'Delete Permissions']) && $role === 'Admin') {
                    $allowed = false;
                }
                
                Permission::firstOrCreate(
                    ['feature' => $feature, 'role_name' => $role],
                    ['allowed' => $allowed]
                );
            }
        }
    }
}