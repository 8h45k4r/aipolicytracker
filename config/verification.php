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
| A record matches the first rule whose `applies` closure returns true, so the
| order below is the policy. Rules marked critical fail `policy:freshness`,
| which the data workflow runs on every change, so the corpus cannot go stale
| unnoticed. The public page at /verification publishes the same numbers.
|
| Ages are days since `last_verified_at` (never verified counts as overdue as
| soon as the record is older than its rule allows).
|
*/

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;

return [

    // Who is accountable for each re-check queue.
    'tracks' => [
        'binding' => ['label' => 'Binding law in force', 'owner' => 'Editor'],
        'pipeline' => ['label' => 'Proposed and pending instruments', 'owner' => 'Editor'],
        'duties' => ['label' => 'Obligations and deadlines', 'owner' => 'Editor'],
        'context' => ['label' => 'Jurisdiction background', 'owner' => 'Editor'],
        'log' => ['label' => 'Change log entries', 'owner' => 'Editor'],
    ],

    'rules' => [
        [
            'id' => 'binding-in-force',
            'label' => 'Binding instruments in force',
            'track' => 'binding',
            'days' => 90,
            'critical' => true,
            'applies' => fn ($record) => $record instanceof PolicyInstrument && $record->is_binding && in_array($record->status, ['in_force', 'partially_in_force'], true),
        ],
        [
            'id' => 'binding-adopted',
            'label' => 'Binding instruments adopted but not yet applying',
            'track' => 'binding',
            'days' => 180,
            'critical' => true,
            'applies' => fn ($record) => $record instanceof PolicyInstrument && $record->is_binding,
        ],
        [
            'id' => 'obligations',
            'label' => 'Obligations',
            'track' => 'duties',
            'days' => 180,
            'critical' => true,
            'applies' => fn ($record) => $record instanceof Obligation,
        ],
        [
            'id' => 'instruments-other',
            'label' => 'Guidance, strategies and standards',
            'track' => 'pipeline',
            'days' => 365,
            'critical' => false,
            'applies' => fn ($record) => $record instanceof PolicyInstrument,
        ],
        [
            'id' => 'changes',
            'label' => 'Change log entries',
            'track' => 'log',
            'days' => 365,
            'critical' => false,
            'applies' => fn ($record) => $record instanceof ChangeEvent,
        ],
        [
            'id' => 'jurisdictions',
            'label' => 'Jurisdiction profiles',
            'track' => 'context',
            'days' => 365,
            'critical' => false,
            'applies' => fn ($record) => $record instanceof Jurisdiction,
        ],
    ],

    // A ratchet, not a target. The check fails when critical breaches exceed this
    // number, so the corpus can improve but never quietly regress. It holds the count
    // measured over the whole corpus on the day the policy was introduced (2026-09-17:
    // 594 records under the policy, 99 of them critical and never confirmed) and may
    // only be lowered: every batch of verifications should be followed by lowering it
    // to the new count. Raising it is a deliberate act argued for in the pull request.
    'critical_budget' => (int) env('VERIFICATION_CRITICAL_BUDGET', 99),
];
