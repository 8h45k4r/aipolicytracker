<?php

/*
| Cross-Origin Resource Sharing. This application serves an Inertia (same-origin)
| front end and exposes no public JSON API, so no origins are allowed by default.
| Set CORS_ALLOWED_ORIGINS (comma-separated) only if you deliberately expose
| endpoints to another origin.
*/

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
