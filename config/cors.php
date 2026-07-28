<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The React SPA (Vite dev server) authenticates via Sanctum's SPA cookie
    | flow, which requires supports_credentials to be true and the frontend
    | origin to be explicitly whitelisted (wildcard "*" is not allowed with
    | credentialed requests).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
