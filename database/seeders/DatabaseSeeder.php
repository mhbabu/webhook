<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Contracts\Role;

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
            UserTableSeeder::class,
            QuickReplyTableSeeder::class,
            WrapUpConversationTableSeeder::class,
            MessageTemplateTableSeeder::class,
            ChatSeeder::class,
            SystemSettingsTableSeeder::class,
        ]);
    }
}
