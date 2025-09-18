<?php

namespace Database\Seeders;

use App\Models\WrapUpConversation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WrapUpConversationTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WrapUpConversation::factory()->count(30)->create();
    }
}
