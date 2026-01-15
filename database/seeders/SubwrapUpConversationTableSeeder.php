<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubwrapUpConversationTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('subwrap_up_conversations')->insert([
            [
                'wrap_up_conversation_id' => 1,
                'name' => 'Customer disconnected abruptly',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wrap_up_conversation_id' => 1,
                'name' => 'Network issue from customer side',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wrap_up_conversation_id' => 2,
                'name' => 'Agent emergency call',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wrap_up_conversation_id' => 2,
                'name' => 'System outage',
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
