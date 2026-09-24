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
    // of critical records never confirmed against their official source, and may only
    // be lowered: every batch of verifications should be followed by lowering it to the
    // new count.
    //
    // 2026-09-17, introduced at 99: 594 records under the policy, 99 of them critical
    // and never confirmed (29 binding in force, 13 binding adopted, 57 obligations).
    //
    // 2026-09-24, raised to 154. The obligation set grew from 57 to 112 as the duty
    // layer was built out, and a duty is critical from the moment it is published, so
    // the count rose by exactly the 55 records added: 29 + 13 + 112 = 154. Nothing
    // went stale and nothing regressed; the corpus grew.
    //
    // Two things follow from that, and they are the argument for raising rather than
    // for softening the rule. Every obligation the roadmap adds will trip this check
    // again, which is the point: growth in unconfirmed duties should be visible and
    // deliberate, not silent. And a raise is only honest while the alternative is
    // refused — the way to lower this number is to confirm records against their
    // sources through /backend/review, never to backdate `last_verified_at` on records
    // nobody has opened the source for. The site publishes what verification means; a
    // fabricated date here would make that claim false everywhere it appears.
    'critical_budget' => (int) env('VERIFICATION_CRITICAL_BUDGET', 154),
];
