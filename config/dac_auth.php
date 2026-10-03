<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Refresh token cookie
    |--------------------------------------------------------------------------
    |
    | Name MUST match REFRESH_TOKEN_COOKIE in the frontend's src/proxy.ts —
    | that's how the frontend's optimistic route guard detects a session
    | without being able to read the HttpOnly value. See
    | docs/auth-api-contract.md §2.
    |
    | Domain must be the shared parent domain (e.g. .dacaccounting.com) in
    | production so the cookie is visible to both the frontend's and this
    | API's origins. Leave null for local development (localhost on any
    | port is treated as the same site by browsers).
    |
    */

    'refresh_cookie_name' => env('REFRESH_COOKIE_NAME', 'dac_refresh_token'),
    'refresh_cookie_domain' => env('REFRESH_COOKIE_DOMAIN'),
    'refresh_cookie_secure' => (bool) env('REFRESH_COOKIE_SECURE', env('APP_ENV') === 'production'),
    'refresh_cookie_samesite' => env('REFRESH_COOKIE_SAMESITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Refresh token lifetime
    |--------------------------------------------------------------------------
    */

    'refresh_token_ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 7),
    'refresh_token_remember_ttl_days' => (int) env('REFRESH_TOKEN_REMEMBER_TTL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Email verification token lifetime
    |--------------------------------------------------------------------------
    */

    'email_verification_ttl_hours' => (int) env('EMAIL_VERIFICATION_TTL_HOURS', 24),

];
