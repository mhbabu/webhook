<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Define roles and the guard
        $roles = [
            'Super Admin' => 'sanctum',
            'Admin'       => 'sanctum',
            'Supervisor'  => 'sanctum',
            'Agent'       => 'sanctum',
        ];

        // Create roles with the 'sanctum' guard
        foreach ($roles as $role => $guard) {
            Role::firstOrCreate(['name' => $role], ['guard_name' => $guard]);
        }

        // Define the hierarchy in a simple array (parent => children)
        $hierarchy = [
            'Super Admin' => ['Admin', 'Supervisor', 'Agent'],
            'Admin'       => ['Supervisor', 'Agent'],
            'Supervisor'  => ['Agent'],
        ];

        // Insert hierarchy relationships into 'role_hierarchy'
        foreach ($hierarchy as $parentRole => $childRoles) {
            $parentRoleId = Role::where('name', $parentRole)->first()->id; // Get parent role id

            // Insert each child role under the parent role
            foreach ($childRoles as $childRole) {
                $childRoleId = Role::where('name', $childRole)->first()->id; // Get child role id
                DB::table('role_hierarchy')->insert([
                    'parent_role_id' => $parentRoleId,
                    'child_role_id' => $childRoleId,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]); // Insert into the 'role_hierarchy' table
            }
        }
    }
}
