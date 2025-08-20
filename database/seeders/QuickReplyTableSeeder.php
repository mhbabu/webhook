<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuickReplyTableSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('quick_replies')->insert([
            ['title' => 'Greeting',      'content' => 'Hello! How can I help you today?', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Welcome',       'content' => 'Welcome aboard! Let me know how I can assist.', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Thanks',        'content' => 'Thank you for reaching out.', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Acknowledged',  'content' => 'Got it, I’m checking this for you.', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Follow-up',     'content' => 'Just following up, do you still need help?', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Closing',       'content' => 'Is there anything else I can help you with?', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Goodbye',       'content' => 'Have a great day ahead!', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Apology',       'content' => 'Sorry for the inconvenience caused.', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Info Request',  'content' => 'Could you please share more details?', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Confirmation',  'content' => 'Your request has been received successfully.', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
