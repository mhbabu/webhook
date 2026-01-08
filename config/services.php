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
        'url' => 'https://graph.facebook.com/v22.0/'.env('WHATSAPP_PHONE_NUMBER_ID').'/messages',
    ],

    'facebook' => [
        'verify_token' => env('FB_VERIFY_TOKEN'), // For webhook verification
        'token' => env('FB_PAGE_ACCESS_TOKEN'), // For Shahidul only need to change later
        'url' => 'https://graph.facebook.com/v18.0',
    ],
    'facebookPage' => [
        'verify_token' => env('FACEBOOK_VERIFY_TOKEN'), // For webhook verification
        'token' => env('FACEBOOK_PAGE_TOKEN'), // For Shahidul only need to change later
    ],
    'instagram' => [
        'ig_business_id' => env('INSTAGRAM_BUSINESS_ID'),
        'ig_page_token' => env('INSTAGRAM_PAGE_TOKEN'),
        'ig_verify_token' => env('INSTAGRAM_VERIFY_TOKEN'),
    ],
    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'organization_id' => env('LINKEDIN_ORGANIZATION_ID'),
    ],
    'graph' => [
        'base_url' => 'https://graph.facebook.com',
        'version' => 'v21.0',
        'system_user_token' => env('SYSTEM_USER_TOKEN'),
    ],
    'conversation' => [
        'conversation_expire_hours' => env('CONVERSATION_EXPIRE_HOURS', 6),
        'website' => [
            'otp_expire_minutes' => env('WEBSITE_CUSTOMER_OTP_EXPIRE_MINUTES', 2),
            'token_expire_minutes' => env('WEBSITE_CUSTOMER_TOKEN_EXPIRE_MINUTES', 10),
        ],
    ],

];
