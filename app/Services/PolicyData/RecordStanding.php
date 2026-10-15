<?php

namespace App\Services\PolicyData;

use App\Enums\PolicyStatus;
use App\Models\DeadlineRevision;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\RecordRevision;
use App\Models\SourceCheck;
use Carbon\CarbonInterface;

/**
 * "Is this still good?" in one line, the way a citator flags a case. The first rule
 * that applies wins, so the strongest signal is the one a reader sees:
 *
 *   1. the instrument is no longer in force (superseded, repealed, archived);
 *   2. a date in it moved in the last 180 days;
 *   3. a change to it was logged in the last 90 days;
 *   4. the record itself was revised in the last 30 days;
 *   5. otherwise: no change recorded as of the last source check or verification;
 *   6. with neither date on file, a neutral line saying so. A good state never
 *      appears without the date it holds for.
 */
final class RecordStanding
{
    /** @return array{tone: string, label: string, detail: ?string, url: ?string, checked_at: ?CarbonInterface, check_outcome: ?string} */
    public static function forPolicy(PolicyInstrument $policy): array
    {
        $check = SourceCheck::latestFor('policy', $policy->slug);
        $base = ['checked_at' => $check?->checked_at ?? $policy->last_checked_at, 'check_outcome' => $check ? SourceCheck::OUTCOMES[$check->outcome] ?? $check->outcome : null, 'url' => null, 'detail' => null];

        $status = PolicyStatus::tryFrom((string) $policy->status);
        if (in_array($status, [PolicyStatus::Superseded, PolicyStatus::Repealed, PolicyStatus::Archived], true)) {
            return ['tone' => 'bad', 'label' => 'No longer in force: '.mb_strtolower($status->label()), 'detail' => $policy->status_note] + $base;
        }

        $moved = DeadlineRevision::where('policy_instrument_id', $policy->id)->where('changed_at', '>=', now()->subDays(180))->latest('changed_at')->get()
            ->first(fn ($r) => $r->from_due_on?->toDateString() !== $r->to_due_on?->toDateString() || ($r->from_label ?? '') !== ($r->to_label ?? ''));
        if ($moved) {
            return ['tone' => 'warn', 'label' => 'A date moved on '.$moved->changed_at->format('j M Y'), 'detail' => $moved->title.': '.($moved->from_due_on?->format('j M Y') ?? ($moved->from_label ?: 'undated')).' → '.($moved->to_due_on?->format('j M Y') ?? ($moved->to_label ?: 'undated'))] + $base;
        }

        $change = $policy->changeEvents()->published()->where('occurred_on', '>=', now()->subDays(90)->toDateString())->latest('occurred_on')->first();
        if ($change) {
            return ['tone' => 'info', 'label' => 'Changed on '.$change->occurred_on->format('j M Y'), 'detail' => $change->title, 'url' => $change->url()] + $base;
        }

        $revision = RecordRevision::for('policy', $policy->slug)->where('changed_at', '>=', now()->subDays(30))->first();
        if ($revision) {
            return ['tone' => 'info', 'label' => 'Record updated on '.$revision->changed_at->format('j M Y'), 'detail' => collect($revision->fields)->pluck('label')->implode(', ')] + $base;
        }

        $since = collect([$check?->checked_at, $policy->last_checked_at, $policy->last_verified_at])->filter()->max();
        if (! $since) {
            return ['tone' => 'neutral', 'label' => 'Not yet checked against its official source'] + $base;
        }

        return ['tone' => 'good', 'label' => 'No change recorded as of '.$since->format('j M Y')] + $base;
    }

    /** A duty stands or falls with its instrument; its own revisions are added on top. */
    public static function forObligation(Obligation $obligation): array
    {
        $standing = $obligation->policyInstrument ? self::forPolicy($obligation->policyInstrument) : ['tone' => 'neutral', 'label' => 'Not yet checked against its official source', 'detail' => null, 'url' => null, 'checked_at' => null, 'check_outcome' => null];
        if (in_array($standing['tone'], ['good', 'neutral'], true)) {
            $revision = RecordRevision::for('obligation', $obligation->slug)->where('changed_at', '>=', now()->subDays(30))->first();
            if ($revision) {
                $standing = ['tone' => 'info', 'label' => 'Duty updated on '.$revision->changed_at->format('j M Y'), 'detail' => collect($revision->fields)->pluck('label')->implode(', '), 'url' => null] + $standing;
            }
        }

        return $standing;
    }
}
