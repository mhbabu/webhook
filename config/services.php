<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'), 
        'token' => env('WHATSAPP_ACCESS_TOKEN'),
        'url'   => 'https://graph.facebook.com/v22.0/' . env('WHATSAPP_PHONE_NUMBER_ID') . '/messages',
    ],

    'facebook' => [
        'fb_verify_token' => env('FB_VERIFY_TOKEN'), 
        'token' => env('FB_PAGE_ACCESS_TOKEN'),
        'url'   => 'https://graph.facebook.com/v18.0',
    ],

    'conversation' => [
        'conversation_expire_hours' => env('CONVERSATION_EXPIRE_HOURS', 6),
       'website' => [
            'otp_expire_minutes' => env('WEBSITE_CUSTOMER_OTP_EXPIRE_MINUTES', 2),
            'token_expire_minutes' => env('WEBSITE_CUSTOMER_TOKEN_EXPIRE_MINUTES', 10),
       ],
    ],

];
