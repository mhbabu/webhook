<?php

return [
    'url'       => env('DISPATCHER_URL', 'http://192.168.30.62:8001/api'),
    'api_key'   => env('DISPATCHER_WHATSAPP_API_KEY', 'z0x6Ye5mBcZg0slX9YvT4hr6ralHYPht'),
    'endpoints' => [
        'authenticate' => '/v1/authenticate-handler',
        'handler'      => '/v1/handler-message',
    ],
];