<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */
'paths' => ['api/*', 'sanctum/csrf-cookie'], // api/* dilei v1 soho shob cover hobe

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',   // Vite dev server
        'http://localhost:5174',   // যদি অন্য পোর্ট ব্যবহার করো
        'http://127.0.0.1:5173',   // Vite dev server via IP
        'http://127.0.0.1:5174',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];