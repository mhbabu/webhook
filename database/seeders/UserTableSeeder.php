<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::create([
            'name'                => 'Super Admin',
            'email'               => 'superadmin@gmail.com',
            'employee_id'         => 'SPAD12345',
            'role_id'             => 1,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'is_password_updated' => 1,
        ]);
        $superAdmin->platforms()->sync([1, 2]); // Attach platforms

        // Admin
        $admin = User::create([
            'name'                => 'Admin',
            'email'               => 'admin@gmail.com',
            'employee_id'         => 'ADMN12345',
            'role_id'             => 2,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'is_password_updated' => 1,
        ]);
        $admin->platforms()->sync([1, 2, 3]);

        // Supervisor
        $supervisor = User::create([
            'name'                => 'Supervisor',
            'email'               => 'supervisor@gmail.com',
            'employee_id'         => 'SPVSR12345',
            'role_id'             => 3,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'is_password_updated' => 1,
        ]);
        $supervisor->platforms()->sync([1, 2, 3]);

        // Agent
        $agent = User::create([
            'name'                => 'Agent',
            'email'               => 'agent@gmail.com',
            'employee_id'         => 'AGNT12345',
            'role_id'             => 4,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'is_password_updated' => 1,
        ]);
        $agent->platforms()->sync([1, 2, 3]);
    }
}
