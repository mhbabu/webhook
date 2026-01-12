<?php

namespace Database\Seeders\Conversation;

use App\Models\ConversationType;
use Illuminate\Database\Seeder;

class ConversationTypeSeeder extends Seeder
{
    public function run(): void
    {
        ConversationType::truncate();

        ConversationType::insert([
            ['name' => 'complain', 'is_active' => true],
            ['name' => 'query', 'is_active' => true],
            ['name' => 'lead', 'is_active' => true],
            ['name' => 'unknown', 'is_active' => true],
        ]);
    }
}
