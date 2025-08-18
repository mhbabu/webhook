<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         DB::table('users')->insert([
            'name'                => 'Super Admin',
            'email'               => 'superadmin@gmail.com',
            'employee_id'         => 'SPAD12345',
            'role_id'             => 1,
            'category_id'         => 1,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'created_at'          => now(),
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'updated_at'          => now()
        ]);

        DB::table('users')->insert([
            'name'                => 'Admin',
            'email'               => 'admin@gmail.com',
            'employee_id'         => 'ADMN12345',
            'role_id'             => 2,
            'category_id'         => 1,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'created_at'          => now(),
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'updated_at'          => now()
        ]);

        DB::table('users')->insert([
            'name'                => 'Supervisor',
            'email'               => 'supervisor@gmail.com',
            'employee_id'         => 'SPVSR12345',
            'category_id'         => 1,
            'role_id'             => 3,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'created_at'          => now(),
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'updated_at'          => now()
        ]);

        DB::table('users')->insert([
            'name'                => 'Agent',
            'email'               => 'agent@gmail.com',
            'employee_id'         => 'AGNT12345',
            'role_id'             => 4,
            'category_id'         => 1,
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'created_at'          => now(),
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'updated_at'          => now()
        ]);
    }
}
