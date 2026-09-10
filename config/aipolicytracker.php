<?php

/*
|--------------------------------------------------------------------------
| AI Policy Tracker application settings
|--------------------------------------------------------------------------
|
| Deployment-specific values live in the environment (.env) rather than in
| source code so the repository can be published without leaking private
| addresses, analytics IDs, or credentials. See .env.example for the list.
|
*/

$csv = static fn (?string $value): array => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

return [

    // Users whose e-mail address appears here can access the /backend admin area.
    'admin_emails' => $csv(env('ADMIN_EMAILS')),

    // Optional Google Analytics 4 measurement ID (e.g. G-XXXXXXXXXX). Empty disables tracking.
    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'),

    // Public links shown in the header/footer. Leave empty to hide the item.
    'links' => [
        'airis' => env('SITE_LINK_AIRIS'),
        'whitepaper' => env('SITE_LINK_WHITEPAPER'),
        'privacy_policy' => env('SITE_LINK_PRIVACY_POLICY'),
        'terms_of_use' => env('SITE_LINK_TERMS_OF_USE'),
    ],

    // Contact e-mail addresses listed on the About page.
    'contact_emails' => $csv(env('CONTACT_EMAILS')),

    // Key contributors listed on the About page. Edit here (not secret) or leave empty.
    // Each entry: ['name' => '...', 'url' => 'https://...', 'icon' => 'fa-brands fa-linkedin']
    'contributors' => [],

    // Upper bound for client-supplied page sizes on JSON list endpoints.
    'max_per_page' => (int) env('MAX_PER_PAGE', 100),
];
