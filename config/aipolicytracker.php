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
    'positioning' => 'AIPolicyTracker is the regulatory intelligence layer for AI governance.',
    'supporting' => 'Monitor source-backed AI policy changes, map every obligation to the controls that meet it and the evidence that proves it, and turn regulatory requirements into practical governance actions.',
    'trust_statement' => 'Every important policy claim should link to a primary official source, show its current status, and display a last-verified date.',
    'disclaimer' => 'Informational only. Nothing on this site is legal advice. Check the linked official sources and consult qualified counsel before acting.',
    'default_og_image' => env('SITE_OG_IMAGE', '/og-default.png'),
    // Dimensions of the default social card. Declared in the markup so Slack, LinkedIn
    // and X reserve the right box before the file loads; only emitted for the default
    // image, because a per-page image may have its own size.
    'default_og_image_width' => (int) env('SITE_OG_IMAGE_WIDTH', 1200),
    'default_og_image_height' => (int) env('SITE_OG_IMAGE_HEIGHT', 630),
    'github_url' => env('SITE_GITHUB_URL', 'https://github.com/8h45k4r/aipolicytracker'),
    'certifyi_url' => env('SITE_CERTIFYI_URL', 'https://certifyi.ai'),
    'newsletter_url' => env('SITE_NEWSLETTER_URL'), // optional external subscription form
    // Official project profiles: footer, email templates and Organization sameAs. Order is display order.
    'social' => [
        ['key' => 'linkedin', 'label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/aipolicytracker/'],
        ['key' => 'x', 'label' => 'X (Twitter)', 'url' => 'https://x.com/aipolicytracker'],
        ['key' => 'facebook', 'label' => 'Facebook', 'url' => 'https://www.facebook.com/aipolicytracker'],
        ['key' => 'instagram', 'label' => 'Instagram', 'url' => 'https://www.instagram.com/aipolicytracker/'],
    ],
    'social_profiles' => $csv(env('SITE_SOCIAL_PROFILES')), // extra verified profile URLs for Organization sameAs
    // The project's X handle, for the `twitter:site` card attribution.
    'x_handle' => env('SITE_X_HANDLE', '@aipolicytracker'),

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
        // Set only to point at a policy hosted elsewhere. Left empty, the site's own
        // /privacy and /terms pages are used; see config/legal.php.
        'privacy_policy' => env('SITE_LINK_PRIVACY_POLICY'),
        'terms_of_use' => env('SITE_LINK_TERMS_OF_USE'),
    ],

    // The project's official mailbox. It is the address on the About page, in the
    // footer, in the Organization structured data, in /.well-known/security.txt and
    // in the API description, so a reader, a crawler and a security researcher all
    // reach the same person. Override per deployment with CONTACT_EMAIL.
    'contact_email' => env('CONTACT_EMAIL', 'bhaskar@aipolicytracker.org'),

    // Every contact address listed on the About page. Defaults to the official
    // mailbox above; set CONTACT_EMAILS to list more than one.
    'contact_emails' => $csv(env('CONTACT_EMAILS')) ?: array_values(array_filter([env('CONTACT_EMAIL', 'bhaskar@aipolicytracker.org')])),

    // Key contributors listed on the About page. Edit here (not secret) or leave empty.
    // Each entry: ['name' => '...', 'url' => 'https://...', 'icon' => 'fa-brands fa-linkedin']
    'contributors' => [],

    // Maintainers shown on the About page.
    'maintainers' => [
        ['name' => 'Bhaskar Bhatt', 'role' => 'Founder and maintainer', 'url' => 'https://bhaskar.com.np/', 'same_as' => ['https://github.com/8h45k4r', 'https://www.linkedin.com/in/8h45k4r/', 'https://x.com/8h45k4r']],
    ],

    // Organisation behind the project (shown on the About page and in structured data).
    'organization' => [
        'name' => 'Dignep Group Pvt. Ltd.',
        'url' => 'https://certifyi.ai',
        'tagline' => 'Powering AI governance for regulated industries',
    ],

    // Datasets and works this site builds on; rendered as references on the About page.
    'references' => [
        ['title' => 'The AI Risk Repository: A comprehensive meta-review, database, and taxonomy of risks from artificial intelligence', 'authors' => 'Slattery, P., Saeri, A. K., Grundy, E. A. C., Graham, J., Noetel, M., Uuk, R., Dao, J., Pour, S., Casper, S., & Thompson, N. (2025)', 'url' => 'https://arxiv.org/abs/2408.12622', 'publisher' => 'MIT AI Risk Initiative, arXiv:2408.12622', 'license' => 'CC BY 4.0'],
        ['title' => 'Preventing Repeated Real World AI Failures by Cataloging Incidents: The AI Incident Database', 'authors' => 'McGregor, S. (2021)', 'url' => 'https://incidentdatabase.ai/', 'publisher' => 'Proceedings of the AAAI Conference on Artificial Intelligence (IAAI-21); Responsible AI Collaborative', 'license' => 'CC BY-SA 4.0'],
        ['title' => 'Ethical and social risks of harm from Language Models', 'authors' => 'Weidinger, L. et al. (2021)', 'url' => 'https://arxiv.org/abs/2112.04359', 'publisher' => 'DeepMind, arXiv:2112.04359', 'license' => null],
        ['title' => 'AI Risk Management Framework (AI RMF 1.0)', 'authors' => 'National Institute of Standards and Technology (2023)', 'url' => 'https://www.nist.gov/itl/ai-risk-management-framework', 'publisher' => 'NIST', 'license' => 'Public domain (US Government work)'],
        ['title' => 'Regulation (EU) 2024/1689 (Artificial Intelligence Act)', 'authors' => 'European Parliament and Council (2024)', 'url' => 'https://eur-lex.europa.eu/eli/reg/2024/1689/oj', 'publisher' => 'Official Journal of the European Union', 'license' => 'EU reuse policy (Decision 2011/833/EU)'],
    ],

    // Shared secret for the scheduled digest trigger (can also be set from the admin settings page).
    'cron_token' => env('CRON_TOKEN'),

    // Upper bound for client-supplied page sizes on JSON list endpoints.
    'max_per_page' => (int) env('MAX_PER_PAGE', 100),
];
