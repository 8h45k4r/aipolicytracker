<?php

/*
|--------------------------------------------------------------------------
| Verification policy
|--------------------------------------------------------------------------
|
| How old a fact may be before it must be checked again. Every published
| record carries the date its facts were last confirmed against the official
| source; this file says what "too old" means for each kind of record and who
| owns the re-check.
|
| The rules themselves live in App\Services\Verification\VerificationRuleset,
| not here: they carry closures, and `php artisan config:cache` cannot
| serialize a closure. Everything in this file must stay serializable.
|
| This file holds who owns each re-check queue and the budget the data check
| enforces. The public page at /verification publishes the same numbers.
|
*/

return [

    // Who is accountable for each re-check queue.
    'tracks' => [
        'binding' => ['label' => 'Binding law in force', 'owner' => 'Editor'],
        'pipeline' => ['label' => 'Proposed and pending instruments', 'owner' => 'Editor'],
        'duties' => ['label' => 'Obligations and deadlines', 'owner' => 'Editor'],
        'context' => ['label' => 'Jurisdiction background', 'owner' => 'Editor'],
        'log' => ['label' => 'Change log entries', 'owner' => 'Editor'],
    ],

    // A ratchet, not a target. The check fails when critical breaches exceed this
    // number, so the corpus can improve but never quietly regress. It holds the count
    // measured over the whole corpus on the day the policy was introduced (2026-09-17:
    // 594 records under the policy, 99 of them critical and never confirmed) and may
    // only be lowered: every batch of verifications should be followed by lowering it
    // to the new count. Raising it is a deliberate act argued for in the pull request.
    'critical_budget' => (int) env('VERIFICATION_CRITICAL_BUDGET', 99),
];
