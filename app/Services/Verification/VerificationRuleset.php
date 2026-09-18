<?php

namespace App\Services\Verification;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;

/**
 * How old a fact may be before it must be checked again, per record type.
 *
 * A record matches the first rule whose `applies` closure returns true, so the
 * order below is the policy. Rules marked critical fail `policy:freshness`,
 * which the data workflow runs on every change, so the corpus cannot go stale
 * unnoticed. The public page at /verification publishes the same numbers.
 *
 * Ages are days since `last_verified_at`; never verified counts as overdue.
 *
 * This lives in app/ rather than config/ because the rules carry closures and
 * `php artisan config:cache` cannot serialize those. When they sat in
 * config/verification.php that command failed, and because the deploy script
 * ran it under `set -e` the container's startup aborted before importing data.
 * Anything in config/ must stay serializable; behaviour belongs here.
 */
class VerificationRuleset
{
    /** @return list<array{id: string, label: string, track: string, days: int, critical: bool, applies: callable}> */
    public static function rules(): array
    {
        return [

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
        ];
    }
}
