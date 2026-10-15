<?php

/*
|--------------------------------------------------------------------------
| The change desk
|--------------------------------------------------------------------------
|
| The routine that keeps the record current: official sources checked on a
| cadence, changes logged, and the speed of logging published as a target.
| See /freshness and Admin → Change desk.
|
*/

return [
    // Days from an official text's date to the change appearing on the site.
    'target_days' => (int) env('CHANGEDESK_TARGET_DAYS', 3),

    // Measurement starts here; earlier first-publication dates come from the bulk import.
    'measured_since' => env('CHANGEDESK_MEASURED_SINCE', '2026-10-16'),

    // How often an instrument's official source is due a look, in days.
    'cadence_days' => [
        'binding' => 7,    // binding instruments that are proposed, adopted or applying
        'other' => 30,     // guidance, strategies and anything else still current
    ],
];
