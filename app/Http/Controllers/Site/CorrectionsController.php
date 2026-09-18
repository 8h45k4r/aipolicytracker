<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ContributorSubmission;
use App\Support\Seo;
use App\Support\SubmissionFieldLabels;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The public corrections log: what readers reported, and what was done about it.
 *
 * A site that invites corrections and never says what happened to them is
 * asking for unpaid work on trust. This page closes that loop, including the
 * reports that were turned down, because a log that only shows accepted
 * corrections is a testimonial rather than a record.
 *
 * What it deliberately does not publish: the submitter's identity, and any free
 * text written by the submitter or written by a reviewer for the internal
 * queue. Those were not written for publication and the site never moderated
 * them for it. Each entry publishes structured facts it can stand behind — what
 * was reported, about which record and field, when, what was decided and when —
 * plus a note the reviewer wrote deliberately for this page, when they wrote one.
 */
class CorrectionsController extends Controller
{
    /** How many entries a single page renders. The log is a record, not an archive browser. */
    private const LIMIT = 100;

    public function show(): View
    {
        // Only submissions a reviewer has actually decided appear. A pending report is
        // an unchecked claim about a record, and publishing those would turn the log
        // into an unmoderated noticeboard.
        $decided = ContributorSubmission::query()
            ->whereIn('status', ['approved', 'rejected', 'needs_information'])
            ->with(['decisions' => fn ($q) => $q->orderByDesc('decided_at')])
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->filter(fn ($s) => $s->decisions->isNotEmpty());

        $entries = $decided->map(function (ContributorSubmission $s) {
            $decision = $s->decisions->first();
            $payload = (array) ($s->payload ?? []);
            $field = (string) ($payload['field'] ?? '');

            return [
                'received_on' => $s->created_at,
                'decided_at' => $decision->decided_at,
                'days' => $s->created_at && $decision->decided_at
                    ? (int) Carbon::parse($s->created_at)->startOfDay()->diffInDays(Carbon::parse($decision->decided_at)->startOfDay())
                    : null,
                'type' => ContributorSubmission::TYPES[$s->type] ?? 'Report',
                'status' => $s->statusEnum(),
                // Resolved live, so an entry never links to a record that has since been
                // withdrawn, and never shows a title captured from an unmoderated field.
                'record' => ContributeController::resolveSubject($s->subject_type, (string) $s->subject_slug),
                'field' => $field !== '' ? SubmissionFieldLabels::label($field) : null,
                'public_note' => $decision->public_note,
            ];
        })->values();

        $counts = ContributorSubmission::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $decidedDays = $entries->pluck('days')->filter(fn ($d) => $d !== null)->sort()->values();

        $seo = Seo::make(
            'Corrections log: what readers reported and what was done about it',
            'Every error report and source proposal a reviewer has decided on, including the ones that were turned down, with the record it concerned and how long the decision took.',
            route('corrections')
        )->withBreadcrumbs([['Home', route('home')], ['Methodology', route('methodology')], ['Corrections', route('corrections')]])
            ->withPageType('CollectionPage');

        return view('site.pages.corrections', [
            'seo' => $seo,
            'entries' => $entries,
            'received' => (int) $counts->sum(),
            'open' => (int) ($counts['pending_review'] ?? 0),
            'accepted' => (int) ($counts['approved'] ?? 0),
            'declined' => (int) ($counts['rejected'] ?? 0),
            // Median, not mean: one report that sat for a year should not be able to
            // describe the typical wait as if it were the usual one.
            'median_days' => $decidedDays->isEmpty() ? null : (int) $decidedDays[intdiv($decidedDays->count(), 2)],
        ]);
    }
}
