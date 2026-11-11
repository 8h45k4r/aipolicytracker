<?php

// The admin sidebar, as data so the layout, the command palette and the tests read one
// source. Three levels, as in a mature admin:
//
//   section (Records)  →  entry with an icon (Review queue)  →  submenu (Jurisdictions)
//
// Sections follow the work, most frequent first. An entry names the capability its page
// requires: one the account cannot open is not shown, and a section with nothing left is
// skipped (a link that answers 403 reads as a broken admin; route middleware still
// enforces access). A submenu entry is the same page with a filter it already accepts
// (`params`), so the menu adds no page and no data. Every section and every submenu
// folds; the ones holding the current page are open. No closures: config:cache must
// serialise this file.
return [
    'sections' => [
        'Overview' => [
            ['route' => 'backend.admin.dashboard', 'label' => 'Dashboard', 'can' => 'dashboard.view', 'icon' => 'home'],
        ],
        'Records' => [
            ['route' => 'backend.admin.changedesk.index', 'label' => 'Change desk', 'can' => 'records.verify', 'icon' => 'refresh', 'children' => [
                ['label' => 'Due now', 'params' => []],
                ['label' => 'All current instruments', 'params' => ['show' => 'all']],
            ]],
            ['route' => 'backend.admin.consultations.index', 'label' => 'Consultations', 'can' => 'records.verify', 'icon' => 'clock'],
            ['route' => 'backend.review.index', 'label' => 'Review queue', 'can' => 'submissions.decide', 'icon' => 'check', 'children' => [
                ['label' => 'Policy instruments', 'params' => ['type' => 'policy']],
                ['label' => 'Jurisdictions', 'params' => ['type' => 'jurisdiction']],
                ['label' => 'Controls', 'params' => ['type' => 'control']],
                ['label' => 'Change log entries', 'params' => ['type' => 'change']],
                ['label' => 'Transition measures', 'params' => ['type' => 'transition_measure']],
                ['label' => 'Implementation measures', 'params' => ['type' => 'implementation_measure']],
            ]],
            ['route' => 'backend.admin.submissions', 'label' => 'Submissions', 'can' => 'submissions.decide', 'icon' => 'inbox', 'children' => [
                ['label' => 'Pending review', 'params' => ['status' => 'pending_review']],
                ['label' => 'Needs information', 'params' => ['status' => 'needs_information']],
                ['label' => 'Approved', 'params' => ['status' => 'approved']],
                ['label' => 'Rejected', 'params' => ['status' => 'rejected']],
            ]],
            ['route' => 'backend.checks.index', 'label' => 'Independent checks', 'can' => 'records.verify', 'icon' => 'scale'],
        ],
        'Content' => [
            ['route' => 'backend.admin.external', 'label' => 'External data', 'can' => 'external.sync', 'icon' => 'database'],
            ['route' => 'backend.admin.tools.index', 'label' => 'Tool library', 'can' => 'tools.manage', 'icon' => 'wrench', 'children' => [
                ['label' => 'All tools', 'params' => []],
                ['label' => 'Add a tool', 'route' => 'backend.admin.tools.create', 'params' => []],
            ]],
            ['route' => 'backend.admin.social.index', 'label' => 'Posts to X', 'can' => 'records.publish', 'icon' => 'megaphone', 'children' => [
                ['label' => 'Awaiting approval', 'params' => ['status' => 'draft']],
                ['label' => 'Queued', 'params' => ['status' => 'queued']],
                ['label' => 'Posted', 'params' => ['status' => 'posted']],
            ]],
        ],
        'Audience' => [
            ['route' => 'backend.admin.subscribers', 'label' => 'Subscribers', 'can' => 'audience.view', 'icon' => 'users', 'children' => [
                ['label' => 'Active', 'params' => ['state' => 'active']],
                ['label' => 'Unconfirmed', 'params' => ['state' => 'unconfirmed']],
                ['label' => 'Unsubscribed', 'params' => ['state' => 'unsubscribed']],
                ['label' => 'Everyone', 'params' => ['state' => 'all']],
            ]],
            ['route' => 'backend.admin.alerts.index', 'label' => 'Alerts and watches', 'can' => 'audience.view', 'icon' => 'bell', 'children' => [
                ['label' => 'All deliveries', 'params' => []],
                ['label' => 'Pending', 'params' => ['status' => 'pending']],
                ['label' => 'Failed', 'params' => ['status' => 'failed']],
            ]],
            ['route' => 'backend.admin.downloads', 'label' => 'Guides and downloads', 'can' => 'audience.view', 'icon' => 'download', 'children' => [
                ['label' => 'Overview', 'params' => ['view' => 'overview']],
                ['label' => 'Requests', 'params' => ['view' => 'requests']],
                ['label' => 'Downloads', 'params' => ['view' => 'downloads']],
                ['label' => 'People', 'params' => ['view' => 'users']],
            ]],
            ['route' => 'backend.admin.enquiries.index', 'label' => 'Licence enquiries', 'can' => 'audience.view', 'icon' => 'mail'],
        ],
        'Operations' => [
            ['route' => 'backend.admin.jobs', 'label' => 'Jobs and schedule', 'can' => 'jobs.run', 'icon' => 'clock'],
            ['route' => 'backend.admin.audit', 'label' => 'Audit log', 'can' => 'audit.view', 'icon' => 'list'],
        ],
        'Administration' => [
            ['route' => 'backend.admin.users.index', 'label' => 'Users and roles', 'can' => 'users.manage', 'icon' => 'shield', 'children' => [
                ['label' => 'All accounts', 'params' => []],
                ['label' => 'Invite a user', 'route' => 'backend.admin.users.invite.create', 'params' => []],
                ['label' => 'Role permissions', 'route' => 'backend.admin.users.permissions', 'params' => []],
            ]],
            ['route' => 'backend.admin.settings', 'label' => 'Settings and API keys', 'can' => 'settings.manage', 'icon' => 'cog'],
            ['route' => 'backend.admin.billing.index', 'label' => 'Billing', 'can' => 'billing.manage', 'icon' => 'card', 'children' => [
                ['label' => 'Subscriptions', 'params' => ['tab' => 'subscriptions']],
                ['label' => 'Payments', 'params' => ['tab' => 'payments']],
                ['label' => 'Webhooks', 'params' => ['tab' => 'webhooks']],
                ['label' => 'Free tier', 'params' => ['tab' => 'quota']],
            ]],
            ['route' => 'backend.admin.funding.index', 'label' => 'Funding and funders', 'can' => 'settings.manage', 'icon' => 'heart'],
        ],
    ],
];
