<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IMAP Accounts
    |--------------------------------------------------------------------------
    |
    | Define your accounts here.
    |
    */
    'accounts' => [
        'gmail' => [
            'host' => 'imap.gmail.com',
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => env('IMAP_USERNAME'),
            'password' => env('IMAP_PASSWORD'),
            'protocol' => 'imap',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Other settings
    |--------------------------------------------------------------------------
    */
    'options' => [
        'fetch' => \Webklex\PHPIMAP\IMAP::FT_PEEK,
        'fetch_order' => 'asc',
        'dispositions' => ['attachment', 'inline'],
        'mask' => '*',
        'attach_dir' => storage_path('app/email_attachments'),
        'idle_timeout' => 0,
        'idle_loop' => false,
    ],

];
