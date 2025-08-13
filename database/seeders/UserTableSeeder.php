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
            'name'                => 'AutoMatrix Supervisor',
            'email'               => 'automatrix@gmail.com',
            'employee_id'         => 'SUP12345',
            'type'                => 'supervisor',
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'created_at'          => now(),
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'updated_at'          => now()
        ]);

        DB::table('users')->insert([
            'name'                => 'AutoMatrix Admin',
            'email'               => 'automatrix1@gmail.com',
            'employee_id'         => 'ADM12345',
            'type'                => 'admin',
            'password'            => bcrypt('12345678'),
            'account_status'      => 'active',
            'created_at'          => now(),
            'email_verified_at'   => now(),
            'is_verified'         => 1,
            'updated_at'          => now()
        ]);
    }
}
