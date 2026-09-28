<?php

// Inertia renders only the account pages (sign-in, registration, profile); the public
// site is Blade. Only what differs from the adapter's defaults is set here.
return [

    // No SSR server runs; the account pages render in the browser.
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
    ],

    // The Breeze pages live in resources/js/Pages (capital P), not the default js/pages.
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['jsx', 'js'],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],

];
