<?php

/*
|--------------------------------------------------------------------------
| Billing (Dodo Payments)
|--------------------------------------------------------------------------
|
| Dodo Payments is the merchant of record: it hosts checkout, stores payment
| methods, calculates tax and handles disputes. This application never sees
| card data. It creates checkout sessions, verifies webhooks and keeps a local
| record of subscriptions to decide entitlements.
|
| Plans are defined here; prices are charged by the Dodo product referenced
| by product_id, so the display price below must match the product. The admin
| billing page compares both and flags a mismatch.
|
| Nothing is sold while BILLING_ENABLED is false: the pricing page shows the
| plans as "not yet available", checkout routes return 404 and the webhook
| endpoint still records events (so a test-mode integration can be verified
| before launch).
|
*/

return [

    'enabled' => (bool) env('BILLING_ENABLED', false),

    // test_mode or live_mode. Also overridable from Admin → Settings.
    'environment' => env('DODO_PAYMENTS_ENVIRONMENT', 'test_mode'),

    'api_key' => env('DODO_PAYMENTS_API_KEY'),
    'webhook_secret' => env('DODO_PAYMENTS_WEBHOOK_KEY'),

    'base_urls' => [
        'test_mode' => 'https://test.dodopayments.com',
        'live_mode' => 'https://live.dodopayments.com',
    ],

    // Days a subscription stays entitled after a renewal payment fails (Dodo status
    // on_hold) so the customer can update the payment method without losing access.
    'on_hold_grace_days' => (int) env('BILLING_ON_HOLD_GRACE_DAYS', 7),

    // Capabilities every visitor and free account has. Paid plans add to these.
    // Only entitlements with a consumer in the code belong here: a key listed but
    // unread is a promise the product does not keep (see docs/reference/technical-debt.md).
    'free' => [
        'name' => 'Free',
        'entitlements' => [
            'alerts.weekly' => true,
            'alerts.daily' => false,
            'saved.server' => false,
        ],
    ],

    'plans' => [
        'pro_monthly' => [
            'name' => 'Pro',
            'interval' => 'month',
            'price' => 2900,          // smallest currency unit; must equal the Dodo product price
            'currency' => 'USD',
            'product_id' => env('DODO_PRODUCT_PRO_MONTHLY'),
            'trial_days' => 0,
            'entitlements' => [
                'alerts.weekly' => true,
                'alerts.daily' => true,
                'saved.server' => true,
            ],
        ],
        'pro_yearly' => [
            'name' => 'Pro (annual)',
            'interval' => 'year',
            'price' => 29000,
            'currency' => 'USD',
            'product_id' => env('DODO_PRODUCT_PRO_YEARLY'),
            'trial_days' => 0,
            'entitlements' => [
                'alerts.weekly' => true,
                'alerts.daily' => true,
                'saved.server' => true,
            ],
        ],
    ],

    // Human-readable lines shown on the pricing page. Only list what exists.
    'features' => [
        'free' => [
            'Every policy, jurisdiction, obligation and change record, with sources',
            'Weekly digest by jurisdiction',
            'Applicability screening, comparisons and open-data downloads',
            'Read-only API at the public rate limit',
        ],
        'pro' => [
            'Follow any policy, jurisdiction or obligation, synced to your account',
            'One daily email when a record you follow changes, with the official source',
            'Application-date reminders 30, 7 and 1 days before they fall due',
        ],
    ],
];
