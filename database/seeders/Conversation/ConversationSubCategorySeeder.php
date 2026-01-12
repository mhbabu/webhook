<?php

namespace Database\Seeders\Conversation;

use App\Models\ConversationCategory;
use App\Models\ConversationSubCategory;
use Illuminate\Database\Seeder;

class ConversationSubCategorySeeder extends Seeder
{
    public function run(): void
    {
        ConversationSubCategory::truncate();

        $serviceIssue = ConversationCategory::where('name', 'Service Issue')->first();
        $productIssue = ConversationCategory::where('name', 'Product Issue')->first();
        $sales = ConversationCategory::where('name', 'Sales')->first();

        ConversationSubCategory::insert([
            // Service Issue
            [
                'conversation_category_id' => $serviceIssue->id,
                'name' => 'Delay',
                'is_active' => true,
            ],
            [
                'conversation_category_id' => $serviceIssue->id,
                'name' => 'Rude Behavior',
                'is_active' => true,
            ],

            // Product Issue
            [
                'conversation_category_id' => $productIssue->id,
                'name' => 'Damaged Product',
                'is_active' => true,
            ],
            [
                'conversation_category_id' => $productIssue->id,
                'name' => 'Wrong Item',
                'is_active' => true,
            ],

            // Sales
            [
                'conversation_category_id' => $sales->id,
                'name' => 'Interested',
                'is_active' => true,
            ],
            [
                'conversation_category_id' => $sales->id,
                'name' => 'Follow Up Required',
                'is_active' => true,
            ],
        ]);
    }
}
