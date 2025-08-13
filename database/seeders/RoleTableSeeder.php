<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        // Permissions for user management
        $permissions = ['user_create', 'user_edit', 'user_update', 'user_delete'];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'sanctum',
            ]);
        }

        // Assign all user permissions to Supervisor role
        $supervisorRole = Role::where('name', 'Supervisor')->first();
        if ($supervisorRole) {
            $supervisorRole->syncPermissions($permissions);
        }

        // Assign Supervisor role to user ID 1
        $user = User::find(1);
        if ($user && $supervisorRole) {
            $user->assignRole($supervisorRole);
        }
    }
}
