<?php

namespace App\Http\Controllers\Backend\Review;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\ContributorSubmission;
use App\Models\RecordVerification;
use App\Models\ReviewerDecision;
use App\Services\Review\ReviewableTypes;
use App\Services\Reviewers\ReviewerRoster;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin-only review queue and publishing controls. Data edits themselves happen
 * in the data/ directory through pull requests; this area handles triage of
 * community submissions, and the review status and publish switch of every
 * imported record kind listed in ReviewableTypes.
 *
 * Every action works on a selection: one row, the rows ticked on the page, or
 * every record matching the current filter. The attestation is made once for the
 * selection and each record still gets its own dated, named verification that
 * survives re-import and exports to data/.
 */
class ReviewController extends Controller
{
    private const PER_PAGE = 50;

    /** The most records one bulk action may touch; larger than any single kind today. */
    private const BULK_LIMIT = 1000;

    private const REVIEW_FILTERS = ['verified', 'pending_review', 'needs_update', 'draft'];

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), SubmissionStatus::values(), true) ? $request->query('status') : 'pending_review';
        $submissions = ContributorSubmission::where('status', $status)->with('decisions.reviewer')->orderByDesc('created_at')->paginate(25, ['*'], 'submissions')->withQueryString();
        $counts = ContributorSubmission::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $type = ReviewableTypes::has($request->query('type')) ? $request->query('type') : 'policy';
        $filters = $this->filters($request);
        $records = $this->filtered($type, $filters)->paginate(self::PER_PAGE)->withQueryString();
        $rows = $records->getCollection()->map(fn (Model $m) => $this->row($type, $m));

        $model = ReviewableTypes::model($type);
        $byReview = $model::selectRaw('review_status, COUNT(*) as n')->groupBy('review_status')->pluck('n', 'review_status');
        $tabs = collect(ReviewableTypes::TYPES)->map(function (array $meta, string $key) {
            $class = $meta['model'];

            return ['key' => $key, 'label' => Str::ucfirst($meta['plural']), 'total' => $class::count(), 'open' => $class::where('review_status', '!=', 'verified')->count()];
        })->values();

        return view('backend.review.index', [
            'submissions' => $submissions, 'status' => $status, 'counts' => $counts,
            'type' => $type, 'meta' => ReviewableTypes::TYPES[$type], 'filters' => $filters, 'records' => $records, 'rows' => $rows,
            'byReview' => $byReview, 'tabs' => $tabs, 'matching' => $records->total(), 'unpublished' => $model::whereNull('published_at')->count(),
            'pendingExport' => RecordVerification::where('exported', false)->count(),
            'reviewStatuses' => ReviewableTypes::REVIEW_STATUSES, 'confidenceLevels' => ReviewableTypes::CONFIDENCE_LEVELS, 'reviewFilters' => self::REVIEW_FILTERS,
        ]);
    }

    public function decide(Request $request, ContributorSubmission $submission): RedirectResponse
    {
        $data = $this->decisionData($request);
        $this->recordDecision($submission, $data, $request->user());

        return back()->with('success', 'Decision recorded and published to the corrections log. Approved submissions must still be applied to the data/ directory through a pull request.');
    }

    /** One decision for every ticked submission: the same notes, the same public note. */
    public function decideMany(Request $request): RedirectResponse
    {
        $data = $this->decisionData($request, ['ids' => ['required', 'array', 'min:1', 'max:'.self::BULK_LIMIT], 'ids.*' => ['integer']]);
        $submissions = ContributorSubmission::whereIn('id', array_unique($data['ids']))->get();
        foreach ($submissions as $submission) {
            $this->recordDecision($submission, $data, $request->user());
        }
        $n = $submissions->count();

        return back()->with('success', $n.' '.Str::plural('submission', $n).' marked '.str_replace('_', ' ', $data['decision']).'. Approved submissions must still be applied to the data/ directory through a pull request.');
    }

    /**
     * Records one reviewer's verification of many records at once.
     *
     * The selection is the ticked rows, or, with scope=filtered, every record the
     * current filter matches, so "everything pending in this jurisdiction" is one act
     * once the reviewer has actually read it.
     */
    public function verifyMany(Request $request, string $type): RedirectResponse
    {
        abort_unless(ReviewableTypes::has($type), 404);
        $data = $request->validate([
            'slugs' => ['required_without:scope', 'array', 'max:'.self::BULK_LIMIT],
            'slugs.*' => ['string', 'max:190'],
            'scope' => ['nullable', 'in:filtered'],
            'review_status' => ['required', 'in:'.implode(',', ReviewableTypes::REVIEW_STATUSES)],
            'confidence_level' => ['required', 'in:'.implode(',', ReviewableTypes::CONFIDENCE_LEVELS)],
            'source_opened' => ['required_if:review_status,verified', 'accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['source_opened.accepted' => 'Confirm that you opened the official source of every selected record before marking them verified.']);

        if ($data['review_status'] === 'verified' && ! $this->isPublishedReviewer($request->user()->name)) {
            return back()->withErrors(['source_opened' => $this->rosterError($request->user()->name)]);
        }

        $recorded = 0;
        foreach ($this->selection($request, $type, $data) as $model) {
            $this->record($type, $model, $data, $request->user());
            $recorded++;
        }
        Cache::flush();

        return back()->with('success', $recorded.' '.ReviewableTypes::label($type, $recorded !== 1).' marked '.str_replace('_', ' ', $data['review_status']).' ('.$data['confidence_level'].') by '.$request->user()->name.'. Run `php artisan policy:export-verifications` and open a pull request to write it into data/.');
    }

    /** Records a human verification (reviewer opened the official source) and applies it to the live row. */
    public function verify(Request $request, string $type, string $slug): RedirectResponse
    {
        $model = $this->reviewable($type, $slug);
        $data = $request->validate([
            'review_status' => ['required', 'in:'.implode(',', ReviewableTypes::REVIEW_STATUSES)],
            'confidence_level' => ['required', 'in:'.implode(',', ReviewableTypes::CONFIDENCE_LEVELS)],
            'source_opened' => ['required_if:review_status,verified', 'accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['source_opened.accepted' => 'Confirm that you opened the official source before marking a record verified.']);

        // A verification is only worth something if the person who made it is named and
        // has published what they are interested in. The data validator refuses a record
        // verified by anyone outside the roster, so refusing it here too keeps the admin
        // from writing a decision that could never be exported.
        if ($data['review_status'] === 'verified' && ! $this->isPublishedReviewer($request->user()->name)) {
            return back()->withErrors(['source_opened' => $this->rosterError($request->user()->name)]);
        }

        $this->record($type, $model, $data, $request->user());
        Cache::flush();

        return back()->with('success', ucfirst($type).' '.$slug.' marked '.$data['review_status'].' ('.$data['confidence_level'].'). Run `php artisan policy:export-verifications` and open a pull request to write it into data/.');
    }

    public function publish(Request $request, string $type, string $slug): RedirectResponse
    {
        $model = $this->reviewable($type, $slug);
        $publish = $request->boolean('publish');
        $this->setPublished($type, $model, $publish);
        Cache::flush();

        return back()->with('success', ($publish ? 'Published ' : 'Unpublished ').$model->slug.'. Remember to mirror the change in data/ (published: '.($publish ? 'true' : 'false').').');
    }

    /** The publish switch for a selection. Unpublishing hides records from the site, API and sitemaps at once. */
    public function publishMany(Request $request, string $type): RedirectResponse
    {
        abort_unless(ReviewableTypes::has($type), 404);
        $data = $request->validate([
            'slugs' => ['required_without:scope', 'array', 'max:'.self::BULK_LIMIT],
            'slugs.*' => ['string', 'max:190'],
            'scope' => ['nullable', 'in:filtered'],
            'publish' => ['required', 'boolean'],
        ]);
        $publish = (bool) $data['publish'];
        $n = 0;
        foreach ($this->selection($request, $type, $data) as $model) {
            $this->setPublished($type, $model, $publish);
            $n++;
        }
        Cache::flush();

        return back()->with('success', ($publish ? 'Published ' : 'Unpublished ').$n.' '.ReviewableTypes::label($type, $n !== 1).'. Remember to mirror the change in data/ (published: '.($publish ? 'true' : 'false').').');
    }

    // ---- selection and filters -------------------------------------------------

    /** @return array{q: string, review: ?string, published: ?string} */
    private function filters(Request $request): array
    {
        $review = $request->input('review');
        $published = $request->input('published');

        return [
            'q' => trim((string) $request->input('q')),
            'review' => in_array($review, self::REVIEW_FILTERS, true) ? $review : null,
            'published' => in_array($published, ['yes', 'no'], true) ? $published : null,
        ];
    }

    /** Unverified and low-confidence first, then by name; narrowed by the filters. */
    private function filtered(string $type, array $filters): Builder
    {
        $title = ReviewableTypes::titleColumn($type);
        $query = ReviewableTypes::query($type);
        if ($filters['q'] !== '') {
            // LOWER on both sides: PostgreSQL's LIKE is case-sensitive and a reviewer
            // typing "eu ai act" should still find the EU AI Act.
            $term = '%'.mb_strtolower($filters['q']).'%';
            $query->where(fn (Builder $q) => $q->whereRaw("LOWER({$title}) LIKE ?", [$term])->orWhereRaw('LOWER(slug) LIKE ?', [$term]));
        }
        if ($filters['review']) {
            $query->where('review_status', $filters['review']);
        }
        if ($filters['published'] === 'yes') {
            $query->whereNotNull('published_at');
        } elseif ($filters['published'] === 'no') {
            $query->whereNull('published_at');
        }
        $query->orderByRaw("CASE review_status WHEN 'verified' THEN 1 ELSE 0 END");

        return match ($type) {
            'policy' => $query->orderBy('confidence_level')->orderBy('title'),
            'change' => $query->orderByDesc('occurred_on'),
            default => $query->orderBy($title),
        };
    }

    /**
     * The records a bulk action applies to: the ticked slugs, or every record the
     * current filter matches when the reviewer asked for the whole filtered set.
     *
     * @return iterable<Model>
     */
    private function selection(Request $request, string $type, array $data): iterable
    {
        if (($data['scope'] ?? null) === 'filtered') {
            return $this->filtered($type, $this->filters($request))->limit(self::BULK_LIMIT)->get();
        }
        $models = [];
        foreach (array_unique($data['slugs'] ?? []) as $slug) {
            if ($model = ReviewableTypes::find($type, $slug)) {
                $models[] = $model;
            }
        }

        return $models;
    }

    /** One display row, the same shape for every kind so the table is one template. */
    private function row(string $type, Model $m): array
    {
        $staleAfter = (int) config('aipolicytracker.stale_after_days', 180);
        $context = match ($type) {
            'policy' => $m->jurisdiction?->name ?? '—',
            'jurisdiction' => $m->region ?: '—',
            'control' => $m->kindLabel().' · '.$m->obligations_count.' '.Str::plural('duty', $m->obligations_count),
            'change' => $m->occurred_on?->format('j M Y').' · '.($m->jurisdiction?->name ?? '—'),
            'transition_measure' => $m->typeLabel().' · '.$m->statusLabel(),
        };
        $note = $type === 'policy' && $m->date_notes && str_contains($m->date_notes, 'not established') ? 'date missing' : null;

        return [
            'slug' => $m->slug,
            'title' => $type === 'policy' ? ($m->short_title ?: $m->title) : $m->{ReviewableTypes::titleColumn($type)},
            'url' => $m->url(),
            'source' => $m->official_source_url ?? null,
            'confidence' => $m->confidence_level,
            'context' => $context,
            'note' => $note,
            'review_status' => $m->review_status,
            'last_verified_at' => $m->last_verified_at,
            'reviewed_by' => $m->reviewed_by ?? null,
            'stale' => $m->review_status === 'verified' && $m->last_verified_at && $m->last_verified_at->lt(now()->subDays($staleAfter)),
            'published' => $m->published_at !== null,
        ];
    }

    // ---- writes ----------------------------------------------------------------

    /** One stored verification, applied to the live row so the site reflects it before the export. */
    private function record(string $type, Model $model, array $data, Authenticatable $user): void
    {
        $verified = $data['review_status'] === 'verified';
        $verification = RecordVerification::updateOrCreate(['record_type' => $type, 'record_slug' => $model->slug], [
            'review_status' => $data['review_status'], 'confidence_level' => $data['confidence_level'], 'last_verified_at' => $verified ? now()->toDateString() : null,
            'reviewed_by' => $user->name, 'source_checked_url' => $model->official_source_url ?? null, 'notes' => $data['notes'] ?? null, 'user_id' => $user->getAuthIdentifier(), 'exported' => false,
        ]);
        $model->forceFill(['review_status' => $verification->review_status, 'confidence_level' => $verification->confidence_level, 'last_verified_at' => $verification->last_verified_at, 'reviewed_by' => $verification->reviewed_by])->save();
    }

    private function setPublished(string $type, Model $model, bool $publish): void
    {
        $model->forceFill(['published_at' => $publish ? now() : null])->save();
        // An instrument's duties are published with it: a hidden instrument with visible
        // obligations would point readers at a record that is not there.
        if ($type === 'policy') {
            $model->obligations()->update(['published_at' => $publish ? now() : null]);
        }
    }

    private function decisionData(Request $request, array $extra = []): array
    {
        return $request->validate($extra + [
            'decision' => ['required', 'in:approved,rejected,needs_information'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Written deliberately for /corrections. `notes` is the internal record and is
            // never published, so a reviewer who wants to say something publicly says it here.
            'public_note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function recordDecision(ContributorSubmission $submission, array $data, Authenticatable $user): void
    {
        ReviewerDecision::create([
            'contributor_submission_id' => $submission->id,
            'reviewer_user_id' => $user->getAuthIdentifier(),
            'decision' => $data['decision'],
            'notes' => $data['notes'] ?? null,
            'public_note' => $data['public_note'] ?? null,
            'decided_at' => now(),
        ]);
        $submission->update(['status' => $data['decision']]);
    }

    private function reviewable(string $type, string $slug): Model
    {
        abort_unless(ReviewableTypes::has($type), 404);

        return ReviewableTypes::model($type)::where('slug', $slug)->firstOrFail();
    }

    private function isPublishedReviewer(?string $name): bool
    {
        return app(ReviewerRoster::class)->published()->contains(fn ($r) => ($r['name'] ?? null) === $name);
    }

    private function rosterError(?string $name): string
    {
        return 'Only a reviewer published on the roster may mark a record verified. Add "'.$name.'" to data/reviewers with a declaration of interest, import, and try again.';
    }
}
