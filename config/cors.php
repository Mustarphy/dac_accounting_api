<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Allows credentialed requests (cookies attached) from the Next.js
    | frontend's exact origin only — never a wildcard, which the spec
    | forbids combining with credentials anyway. Add further production
    | origins to FRONTEND_URL's env value (comma-separated) if ever
    | needed; this intentionally stays a short explicit allow-list rather
    | than a pattern match.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
