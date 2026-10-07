<?php

/*
|--------------------------------------------------------------------------
| Funding and independence
|--------------------------------------------------------------------------
|
| What /funding publishes. Every funder giving more than the threshold in a
| year is listed with the amount and what it pays for; an entry is added when
| the money is agreed, not when it arrives. Leave a list empty rather than
| inventing an entry: the page says plainly that there is none.
|
*/

return [

    // Disclosure threshold in US dollars per year.
    'disclosure_threshold' => 1000,

    // Each: ['name', 'kind' (grant|sponsor|subscription), 'amount', 'period', 'purpose', 'url' (optional)]
    'funders' => [],

    // Public sponsorship page (GitHub Sponsors or a fiscal host). Shown only when set.
    'sponsor_url' => env('SPONSOR_URL'),

];
