<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Cloudflare Turnstile on the template download form. Both keys come from the
    // Cloudflare dashboard (Turnstile → Add site). Unset, the check is skipped.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    // IndexNow tells Bing (and through it Copilot and ChatGPT search), Yandex, Naver and
    // Seznam which URLs changed, instead of waiting for a recrawl. The key is any 8 to 128
    // letters, digits or dashes (`openssl rand -hex 16`); it is served at /{key}.txt so
    // the search engine can check the submission came from this host. Unset, nothing is sent.
    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
        'endpoint' => env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // AI Incident Database live API. Since October 2026 incident queries need a signed-in
    // account; set one of these if AIID gives you access, otherwise the daily sync stands
    // down and the weekly public backup (external:sync-aiid) keeps incidents current.
    'aiid' => [
        'api_token' => env('AIID_API_TOKEN'),
        'api_cookie' => env('AIID_API_COOKIE'),
    ],

];
