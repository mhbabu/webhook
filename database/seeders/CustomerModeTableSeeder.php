<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerModeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('customer_modes')->insert([
            [
                'name' => 'Positive',
            ],
            [
                'name' => 'Negative',
            ],
            [
                'name' => 'Neutral',
            ],
        ]);
    }
}
