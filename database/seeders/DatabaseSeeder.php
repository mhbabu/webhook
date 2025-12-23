<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleTableSeeder::class,
            PlatformTableSeeder::class,
            PlatformAccountSeeder::class,
            UserTableSeeder::class,
            QuickReplyTableSeeder::class,
            WrapUpConversationTableSeeder::class,
            MessageTemplateTableSeeder::class,
            // ChatSeeder::class,
            SystemSettingsTableSeeder::class,
        ]);
    }
}
