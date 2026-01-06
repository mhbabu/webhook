<?php

namespace Database\Seeders;

use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Database\Seeder;

class PlatformAccountSeeder extends Seeder
{
    public function run()
    {
        // Fetch platforms
        $facebookPlatform = Platform::where('name', 'facebook')->first();
        $instagramPlatform = Platform::where('name', 'instagram')->first();
        $instagramMsgPlatform = Platform::where('name', 'instagram_message')->first();
        $whatsappPlatform = Platform::where('name', 'whatsapp')->first();
        $emailPlatform = Platform::where('name', 'email')->first();
        $messengerPlatform = Platform::where('name', 'facebook_messenger')->first();

        // Facebook Page Account
        PlatformAccount::create([
            'platform_id' => $facebookPlatform->id,
            'platform_account_id' => env('FACEBOOK_PAGE_ID'),
            'name' => 'Facebook Page',
            'username' => null,
            'credentials' => [
                'page_token' => env('FACEBOOK_PAGE_TOKEN'),
                'app_id' => env('FACEBOOK_APP_ID'),
                'app_secret' => env('FACEBOOK_APP_SECRET'),
                'verify_token' => env('FACEBOOK_VERIFY_TOKEN'),
                'user_token' => env('FACEBOOK_USER_TOKEN'),
            ],
        ]);

        // Facebook Messenger (same page ID but different purpose)
        PlatformAccount::create([
            'platform_id' => $messengerPlatform->id,
            'platform_account_id' => env('FACEBOOK_PAGE_ID'),
            'name' => 'Facebook Messenger',
            'username' => null,
            'credentials' => [
                'page_token' => env('FACEBOOK_PAGE_TOKEN'),
                'verify_token' => env('FACEBOOK_VERIFY_TOKEN'),
            ],
        ]);

        // Instagram Basic + Comments (linked to same page)
        PlatformAccount::create([
            'platform_id' => $instagramPlatform->id,
            'platform_account_id' => env('INSTAGRAM_BUSINESS_ID'),
            'name' => 'Instagram Business Account',
            'username' => null,
            'credentials' => [
                'page_token' => env('FACEBOOK_PAGE_TOKEN'),
                'verify_token' => env('INSTAGRAM_VERIFY_TOKEN'),
            ],
        ]);

        // Instagram Messaging API
        PlatformAccount::create([
            'platform_id' => $instagramMsgPlatform->id,
            'platform_account_id' => env('INSTAGRAM_BUSINESS_ID'),
            'name' => 'IG Message Account',
            'username' => null,
            'credentials' => [
                'page_token' => env('FACEBOOK_PAGE_TOKEN'),
                'verify_token' => env('INSTAGRAM_VERIFY_TOKEN'),
            ],
        ]);

        // WhatsApp Cloud API
        PlatformAccount::create([
            'platform_id' => $whatsappPlatform->id,
            'platform_account_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'name' => 'WhatsApp Number',
            'username' => null,
            'credentials' => [
                'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
                'token' => env('FACEBOOK_PAGE_TOKEN'),
                'verify_token' => env('INSTAGRAM_VERIFY_TOKEN'),
            ],
        ]);

        // Email (optional)
        PlatformAccount::create([
            'platform_id' => $emailPlatform->id,
            'platform_account_id' => 'imap.gmail.com',
            'name' => 'Gmail Integration',
            'username' => 'akand.shahidul@gmail.com',
            'credentials' => [
                'imap_host' => env('IMAP_HOST'),
                'imap_username' => env('IMAP_USERNAME'),
                'imap_password' => env('IMAP_PASSWORD'),
            ],
        ]);
    }
}
