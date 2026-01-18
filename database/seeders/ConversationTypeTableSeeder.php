<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConversationTypeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('conversation_types')->insert([
            ['name' => 'Complain'],
            ['name' => 'Query'],
            ['name' => 'Lead'],
            ['name' => 'Unknown'],
        ]);
    }
}
