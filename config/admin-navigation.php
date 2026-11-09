<?php

// The admin sidebar, as data so the layout, the command palette and the tests read one
// source. Groups follow the work, most frequent first: the record routine (Records),
// what the site publishes beyond records (Content), the people it serves (Audience),
// keeping it running (Operations) and who may do what (Administration).
//
// Each entry is [route name, label, capability]. An entry the account cannot open is
// not shown, and a group with nothing left is skipped: a link that answers 403 reads as
// a broken admin. Route middleware still enforces access. Every group collapses; the one
// holding the current page is always open. No closures: config:cache must serialise this.
return [
    'groups' => [
        'Overview' => [
            ['backend.admin.dashboard', 'Dashboard', 'dashboard.view'],
        ],
        'Records' => [
            ['backend.admin.changedesk.index', 'Change desk', 'records.verify'],
            ['backend.review.index', 'Review queue', 'submissions.decide'],
            ['backend.admin.submissions', 'Submissions and feedback', 'submissions.decide'],
            ['backend.checks.index', 'Independent checks', 'records.verify'],
        ],
        'Content' => [
            ['backend.admin.external', 'External data', 'external.sync'],
            ['backend.admin.tools.index', 'Tool library', 'tools.manage'],
            ['backend.admin.social.index', 'Posts to X', 'records.publish'],
        ],
        'Audience' => [
            ['backend.admin.subscribers', 'Subscribers', 'audience.view'],
            ['backend.admin.alerts.index', 'Alerts and watches', 'audience.view'],
            ['backend.admin.downloads', 'Guides and downloads', 'audience.view'],
            ['backend.admin.enquiries.index', 'Licence enquiries', 'audience.view'],
        ],
        'Operations' => [
            ['backend.admin.jobs', 'Jobs and schedule', 'jobs.run'],
            ['backend.admin.audit', 'Audit log', 'audit.view'],
        ],
        'Administration' => [
            ['backend.admin.users.index', 'Users and roles', 'users.manage'],
            ['backend.admin.settings', 'Settings and API keys', 'settings.manage'],
            ['backend.admin.billing.index', 'Billing', 'billing.manage'],
            ['backend.admin.funding.index', 'Funding and funders', 'settings.manage'],
        ],
    ],
];
