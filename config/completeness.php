<?php

/*
|--------------------------------------------------------------------------
| Completeness policy
|--------------------------------------------------------------------------
|
| What a published record must carry to be worth publishing. The verification
| policy (config/verification.php) measures how OLD a record's facts are; this
| file measures whether the facts are THERE at all. The two are deliberately
| separate: a record checked yesterday can still be missing its source link,
| and a complete record can still be years out of date.
|
| The checks themselves live in App\Services\Completeness\CompletenessChecks,
| not here: they carry closures, and `php artisan config:cache` cannot
| serialize a closure. Everything in this file must stay serializable.
|
| Each check names one field or relationship, the record kind it applies to,
| and a severity:
|
|   required  A record should not be published without it. Required gaps are
|             what `policy:coverage` ratchets on, so the corpus cannot quietly
|             get thinner.
|   expected  The record works without it but is less useful. Counted and
|             published, never gated, because gating it would push editors
|             towards filling boxes rather than checking facts.
|
| `missing` returns true when the check fails. `applies` narrows a check to
| the records it makes sense for; a check with no `applies` covers every
| published record of its kind. Both receive the model.
|
| Every gap published at /gaps links to the correction form with the record
| and the field already selected, so a reader can close one without an account.
|
*/

return [

    'kinds' => [
        'policy' => ['label' => 'Policy instruments', 'plural' => 'instruments'],
        'obligation' => ['label' => 'Obligations', 'plural' => 'obligations'],
        'change' => ['label' => 'Change log entries', 'plural' => 'entries'],
        'transition_measure' => ['label' => 'Transition measures', 'plural' => 'measures'],
        'jurisdiction' => ['label' => 'Jurisdiction profiles', 'plural' => 'profiles'],
    ],

    /*
    | A ratchet, not a target, on the same terms as the verification budget: the
    | check fails when required gaps exceed this number, so completeness can improve
    | but never quietly regress. It holds the count measured over the whole corpus on
    | the day the policy was introduced and may only be lowered. Raising it is a
    | deliberate act argued for in the pull request.
    |
    | 2026-09-26, raised from 0 to 5. The five duties of Colorado SB 26-189 were
    | recorded from secondary reporting, which names what each duty requires but
    | not the section of the enrolled bill it sits in. Filling source_reference
    | from anything but the bill would be inventing a legal citation, so the field
    | stays empty, the gap shows on /gaps, and this number returns to 0 when a
    | reviewer reads the bill and records the sections.
    */
    'required_budget' => (int) env('COMPLETENESS_REQUIRED_BUDGET', 5),
];
