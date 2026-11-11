<?php

/*
| Official consultations and comment deadlines on AI (docs/modules/consultations.md).
| Only official sources: an entry is collected from the publisher's own API and links
| the publisher's page. Collected entries are shown as not reviewed until a reviewer
| confirms them in the admin.
*/

return [
    'enabled' => (bool) env('CONSULTATIONS_SYNC', true),

    'federal_register' => [
        // The Federal Register API (federalregister.gov), published by the US Office of the
        // Federal Register. Public, no key.
        'base_url' => 'https://www.federalregister.gov/api/v1/documents.json',
        'terms' => ['"artificial intelligence"', '"automated decision"', '"machine learning"'],
        'max_pages' => 5,
        'timeout' => 20,
    ],

    // The API's full-text search matches a word anywhere in a long rule, so an entry is
    // kept only when its title or abstract is about AI.
    'relevance' => '/artificial intelligence|\bA\.?I\.?\b|machine learning|automated decision|algorithmic|generative model|large language model/i',
];
