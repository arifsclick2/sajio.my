<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,https://app.sajio.my,https://sajio.my,https://www.sajio.my')),

    /*
     * Every restaurant owns {subdomain}.sajio.my — ordering pages, QR links
     * and (later) landing pages live there, so those origins must be allowed
     * for the public menu/order API and dashboard API calls (§25 tenant via
     * subdomain). Exact hosts above remain; this pattern covers the wildcard.
     */
    'allowed_origins_patterns' => [
        '#^https://([a-z0-9-]+\.)?sajio\.my$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
