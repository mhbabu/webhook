<?php

namespace Database\Seeders\Conversation;

use App\Models\ConversationCategory;
use Illuminate\Database\Seeder;

class ConversationCategorySeeder extends Seeder
{
    public function run(): void
    {
        ConversationCategory::truncate();

        ConversationCategory::insert([
            ['name' => 'Service Issue', 'is_active' => true],
            ['name' => 'Product Issue', 'is_active' => true],
            ['name' => 'General Inquiry', 'is_active' => true],
            ['name' => 'Sales', 'is_active' => true],
        ]);
    }
}
