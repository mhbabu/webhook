<?php

return [
    'url'               => env('DISPATCHER_URL', 'http://192.168.30.62:8001/api'),
    'whatsapp_api_key'  => env('DISPATCHER_WHATSAPP_API_KEY', 'z0x6Ye5mBcZg0slX9YvT4hr6ralHYPht'),
    'facebook_api_key'  => env('DISPATCHER_FACEBOOK_API_KEY', 'RgszhyQMJvhtpvZ7Kemg3TcCD6EqvsNj'),
    'messenger_api_key' => env('DISPATCHER_MESSENGER_API_KEY', 'hB3fDFSzas35dYL1S8Ojyd8LsE1CG0dQ'),
    'endpoints' => [
        'authenticate' => '/v1/authenticate-handler',
        'handler'      => '/v1/handler-message',
    ],
];