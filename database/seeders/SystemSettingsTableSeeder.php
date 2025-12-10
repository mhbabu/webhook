<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;
use Illuminate\Support\Str;

class SystemSettingsTableSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'whatsapp' => [
                'token' => Str::random(64),                    // random fake token
                'phone_number_id' => '1234567890',            // fake phone number id
                'business_account_id' => '9876543210',        // fake business account id
            ],

            'facebook' => [
                'verify_token' => Str::random(16),           // fake verify token
                'page_access_token' => Str::random(64),      // fake page token
            ],

            'instagram' => [
                'verify_token' => Str::random(16),           // fake verify token
                'graph_token' => Str::random(64),            // fake graph token
            ],
        ];

        foreach ($settings as $key => $value) {
            SystemSetting::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
        }
    }
}
