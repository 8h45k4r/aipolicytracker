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

    // ---------------------------------------------------------------------
    // Product identity and positioning (used in metadata and structured data)
    // ---------------------------------------------------------------------
    'site_name' => env('SITE_NAME', 'AIPolicyTracker'),
    'tagline' => 'Track AI policy. Build compliant AI.',
    'positioning' => 'AIPolicyTracker turns AI policy into practical compliance action.',
    'supporting' => 'Explore source-backed AI laws, regulations, standards, public-sector guidance, obligations, deadlines, and implementation actions across jurisdictions.',
    'trust_statement' => 'Every important policy claim should link to a primary official source, show its current status, and display a last-verified date.',
    'disclaimer' => 'Informational only. Nothing on this site is legal advice. Check the linked official sources and consult qualified counsel before acting.',
    'default_og_image' => env('SITE_OG_IMAGE', '/og-default.png'),
    'github_url' => env('SITE_GITHUB_URL', 'https://github.com/8h45k4r/aipolicytracker'),
    'certifyi_url' => env('SITE_CERTIFYI_URL', 'https://certifyi.ai'),
    'newsletter_url' => env('SITE_NEWSLETTER_URL'), // optional external subscription form
    'social_profiles' => $csv(env('SITE_SOCIAL_PROFILES')), // verified profile URLs for Organization sameAs

    // ---------------------------------------------------------------------
    // Open data
    // ---------------------------------------------------------------------
    'data_license' => 'CC BY 4.0',
    'data_license_url' => 'https://creativecommons.org/licenses/by/4.0/',
    'data_schema_version' => '1.0.0',
    'citation' => 'AIPolicyTracker (year). "Record title". https://aipolicytracker.org/... (accessed date). Data licensed CC BY 4.0.',
    'stale_after_days' => 180,

    // ---------------------------------------------------------------------
    // Analytics and search-engine verification (all optional)
    // ---------------------------------------------------------------------
    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'),
    'cloudflare_analytics_token' => env('CLOUDFLARE_ANALYTICS_TOKEN'),
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    'bing_site_verification' => env('BING_SITE_VERIFICATION'),
    // When true, analytics scripts load only after the visitor accepts the consent banner.
    'analytics_require_consent' => (bool) env('ANALYTICS_REQUIRE_CONSENT', true),

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

    // Maintainers shown on the About page.
    'maintainers' => [
        ['name' => 'Bhaskar Bhatt', 'role' => 'Founder and maintainer', 'url' => 'https://github.com/8h45k4r'],
    ],

    // Upper bound for client-supplied page sizes on JSON list endpoints.
    'max_per_page' => (int) env('MAX_PER_PAGE', 100),
];
