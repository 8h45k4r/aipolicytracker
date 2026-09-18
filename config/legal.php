<?php

/*
|--------------------------------------------------------------------------
| Privacy policy and terms of use
|--------------------------------------------------------------------------
|
| The published pages are built from this file and from the application's real
| schema, so the inventory of what is stored can be checked against the
| migrations rather than taken on trust.
|
| Two values are deliberately null by default: the address for privacy requests
| and the governing law. Neither can be derived from the codebase, and a legal
| page that states an invented contact or an invented jurisdiction is worse than
| one that omits the clause. The pages render without them and the clauses
| appear as soon as they are set.
|
*/

return [

    // The entity that operates the site and controls the personal data.
    'controller' => [
        'name' => env('LEGAL_ENTITY_NAME', 'Dignep Group Pvt. Ltd.'),
        'url' => env('LEGAL_ENTITY_URL', 'https://certifyi.ai'),
        // Registered address, shown in full on the privacy page when set.
        'address' => env('LEGAL_ENTITY_ADDRESS'),
    ],

    // Where a reader sends an access, correction or erasure request. Falls back
    // to CONTACT_EMAILS, then to the public contribution form.
    'privacy_contact' => env('LEGAL_PRIVACY_EMAIL'),

    // e.g. "Nepal" or "India". The governing-law clause is omitted while unset,
    // because naming the wrong courts is worse than naming none.
    'governing_law' => env('LEGAL_GOVERNING_LAW'),

    // Shown as "last updated" on both pages. Bump when the text changes.
    'effective_from' => env('LEGAL_EFFECTIVE_FROM', '2026-09-18'),

    // How long each kind of record is kept. Rendered as the retention table, so
    // the published promise and the operational rule are the same string.
    'retention' => [
        ['what' => 'Account record (name, address, organisation)', 'how_long' => 'Until you delete the account. Deletion removes it immediately.'],
        ['what' => 'Download history', 'how_long' => 'Until you delete the account.'],
        ['what' => 'Newsletter subscription', 'how_long' => 'Until you unsubscribe, which removes the address from the sending list.'],
        ['what' => 'Contribution you submitted', 'how_long' => 'Kept indefinitely as part of the public correction record. Your name and address are never published with it.'],
        ['what' => 'Aggregate page counts', 'how_long' => 'Kept indefinitely. They contain no identifier, so there is nothing in them to attribute to you.'],
        ['what' => 'Administrator action log', 'how_long' => 'Kept indefinitely. It records staff actions, not reader activity, and exists so an editorial decision can be traced to a person.'],
    ],
];
