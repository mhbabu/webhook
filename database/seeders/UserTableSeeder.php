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
            'max_limit'           => 5,
            'current_limit'       => 5
        ]);
        $superAdmin->platforms()->sync([1, 2, 3, 4]); // Attach platforms

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
            'max_limit'           => 5,
            'current_limit'       => 5
        ]);
        $admin->platforms()->sync([1, 2, 3, 4]);

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
            'max_limit'           => 5,
            'current_limit'       => 5
        ]);
        $supervisor->platforms()->sync([1, 2, 3, 4]);

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
            'max_limit'           => 5,
            'current_limit'       => 5
        ]);
        $agent->platforms()->sync([1, 2, 3]);

        // Agent
        $agent = User::create([
            'name'                => 'Shahidul Islam',
            'email'               => 'shahidul@gmail.com',
            'employee_id'         => 'AGNT123452',
            'role_id'             => 4,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'is_password_updated' => 1,
            'max_limit'           => 2,
            'current_limit'       => 2
        ]);
        $agent->platforms()->sync([2, 3]);

        $agent = User::create([
            'name'                => 'Imran Islam',
            'email'               => 'imran@gmail.com',
            'employee_id'         => 'AGNT123453',
            'role_id'             => 4,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'is_password_updated' => 1,
            'max_limit'           => 2,
            'current_limit'       => 2
        ]);
        $agent->platforms()->sync([1]);

        $agent = User::create([
            'name'                => 'Rashed Khan',
            'email'               => 'rashed@gmail.com',
            'employee_id'         => 'AGNT12345309',
            'role_id'             => 4,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'is_password_updated' => 1,
            'max_limit'           => 2,
            'current_limit'       => 2
        ]);
        $agent->platforms()->sync([1]);
    }
}
