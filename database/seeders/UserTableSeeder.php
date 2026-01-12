<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $users = [
            // Super Admin
            [
                'name'                => 'Super Admin',
                'email'               => 'superadmin@gmail.com',
                'employee_id'         => 'SPAD12345',
                'role_id'             => 1,
                'password'            => bcrypt('12345678'),
                'account_status'      => 'active',
                'email_verified_at'   => $now,
                'is_verified'         => 1,
                'is_password_updated' => 1,
                'max_limit'           => 5,
                'available_scope'     => 5,
                'current_limit'       => 0,
                'platforms'           => [1, 2, 3, 4],
            ],

            // Admin
            [
                'name'                => 'Admin',
                'email'               => 'admin@gmail.com',
                'employee_id'         => 'ADMN12345',
                'role_id'             => 2,
                'password'            => bcrypt('12345678'),
                'account_status'      => 'active',
                'email_verified_at'   => $now,
                'is_verified'         => 1,
                'is_password_updated' => 1,
                'max_limit'           => 5,
                'available_scope'     => 5,
                'current_limit'       => 0,
                'platforms'           => [1, 2, 3, 4, 5, 6, 7],
            ],

            // Supervisor
            [
                'name'                => 'Supervisor',
                'email'               => 'supervisor@gmail.com',
                'employee_id'         => 'SPVSR12345',
                'role_id'             => 3,
                'password'            => bcrypt('12345678'),
                'account_status'      => 'active',
                'email_verified_at'   => $now,
                'is_verified'         => 1,
                'is_password_updated' => 1,
                'max_limit'           => 5,
                'available_scope'     => 5,
                'current_limit'       => 0,
                'platforms'           => [1, 2, 3, 4],
            ],
        ];

        // Bangladeshi agent names
        $agentNames = [
            'Shahidul Islam', 'Imran Hossain', 'Rashed Khan', 'Tanvir Rahman',
            'Ashraf Uddin', 'Fahim Hasan', 'Nayeem Chowdhury', 'Sabbir Ahmed',
            'Mehedi Hasan', 'Rakibul Islam', 'Jahidul Islam', 'Saimon Hossain',
            'Shahriar Khan', 'Arifuzzaman', 'Sujon Miah', 'Riyad Chowdhury',
            'Tariq Hossain', 'Shuvro Das', 'Tanvir Alam', 'Jubayer Hossain',
        ];

        foreach ($agentNames as $index => $name) {
            $value = rand(2, 5);
            $users[] = [
                'name'                => $name,
                'email'               => 'agent' . ($index + 1) . '@gmail.com',
                'employee_id'         => 'AGNT' . str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'role_id'             => 4,
                'password'            => bcrypt('12345678'),
                'account_status'      => 'active',
                'email_verified_at'   => $now,
                'is_verified'         => 1,
                'is_password_updated' => 1,
                'max_limit'           => $value,
                'available_scope'     => $value,
                'current_limit'       => 0,
                'platforms'           => [1, 2, 3, 5], // customize if needed
                'mobile'              => $this->generateBangladeshiPhone(),
            ];
        }

        // Seed all users
        foreach ($users as $userData) {
            $platforms = $userData['platforms'];
            unset($userData['platforms']);

            $userData['created_at'] = $now;
            $userData['updated_at'] = $now;

            $user = User::create($userData);
            $user->platforms()->sync($platforms);
        }
    }

    /**
     * Generate a random Bangladeshi mobile number.
     */
    private function generateBangladeshiPhone(): string
    {
        return '+8801' . rand(3, 9) . str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    }
}
