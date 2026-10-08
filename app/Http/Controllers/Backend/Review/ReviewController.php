<?php

namespace App\Http\Controllers\Backend\Review;

use App\Http\Controllers\Controller;
use App\Models\ContributorSubmission;
use App\Models\EnforcementEvent;
use App\Models\RecordVerification;
use App\Models\ReviewerDecision;
use App\Services\Review\ReviewableTypes;
use App\Services\Reviewers\ReviewerRoster;
use App\Support\Admin\BulkAction;
use App\Support\Admin\CsvStream;
use App\Support\Admin\SubmissionQuery;
use App\Support\ContentCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** The bulk bar's default for review status and confidence: leave each record's value as it is. */
    public const KEEP = 'keep';

    /** 'stale' is not a stored status: never verified, or verified longer ago than the stale threshold in settings. */
    private const REVIEW_FILTERS = ['stale', 'verified', 'pending_review', 'needs_update', 'draft'];

    public function index(Request $request): View
    {
        // Submissions are decided on the Submissions page; the queue only says what is waiting
        // and links there, so there is one place, and one form, for each decision.
        $waiting = ContributorSubmission::where('status', 'pending_review')->orderByDesc('created_at')->limit(5)->get();
        $counts = ContributorSubmission::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        // Whether the signed-in reviewer may sign a verification is known before the form is
        // drawn, so someone off the roster is told why instead of meeting a refusal on submit.
        $onRoster = $this->isPublishedReviewer($request->user()->name);

        $type = ReviewableTypes::has($request->query('type')) ? $request->query('type') : 'policy';
        $filters = $this->filters($request);
        $records = $this->filtered($type, $filters)->paginate(self::PER_PAGE)->withQueryString();
        $rows = $records->getCollection()->map(fn (Model $m) => $this->row($type, $m));

        $model = ReviewableTypes::model($type);
        $byReview = $model::selectRaw('review_status, COUNT(*) as n')->groupBy('review_status')->pluck('n', 'review_status');
        $byReview['stale'] = $model::where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays(self::staleAfter())))->count();
        $tabs = collect(ReviewableTypes::TYPES)->map(function (array $meta, string $key) {
            $class = $meta['model'];

            return ['key' => $key, 'label' => Str::ucfirst($meta['plural']), 'total' => $class::count(), 'open' => $class::where('review_status', '!=', 'verified')->count()];
        })->values();

        return view('backend.review.index', [
            'waiting' => $waiting, 'counts' => $counts, 'onRoster' => $onRoster,
            'type' => $type, 'meta' => ReviewableTypes::TYPES[$type], 'filters' => $filters, 'records' => $records, 'rows' => $rows,
            'byReview' => $byReview, 'tabs' => $tabs, 'matching' => $records->total(), 'unpublished' => $model::whereNull('published_at')->count(),
            'pendingExport' => RecordVerification::where('exported', false)->count(),
            'reviewStatuses' => $onRoster ? ReviewableTypes::REVIEW_STATUSES : array_values(array_diff(ReviewableTypes::REVIEW_STATUSES, ['verified'])),
            'keep' => self::KEEP, 'bulkLimit' => self::BULK_LIMIT, 'confidenceLevels' => ReviewableTypes::CONFIDENCE_LEVELS, 'reviewFilters' => self::REVIEW_FILTERS,
            'pendingEvents' => $this->pendingEnforcementEvents(),
        ]);
    }

    /** The records the current tab and filters show, as CSV, for working through offline. */
    public function export(Request $request): StreamedResponse
    {
        $type = ReviewableTypes::has($request->query('type')) ? (string) $request->query('type') : 'policy';
        $title = ReviewableTypes::titleColumn($type);
        $staleAfter = self::staleAfter();

        return CsvStream::from($this->filtered($type, $this->filters($request)), 'review-'.$type,
            ['slug', 'title', 'review_status', 'confidence', 'last_verified_at', 'reviewed_by', 'stale', 'published', 'source'],
            fn (Model $m) => [$m->slug, $m->{$title}, $m->review_status, $m->confidence_level ?? null, $m->last_verified_at, $m->reviewed_by ?? null,
                ! $m->last_verified_at || $m->last_verified_at->lt(now()->subDays($staleAfter)), $m->published_at !== null, $m->official_source_url ?? null]);
    }

    public function decide(Request $request, ContributorSubmission $submission): RedirectResponse
    {
        $data = $this->decisionData($request, 'submission-'.$submission->id);
        $this->recordDecision($submission, $data, $request->user());

        return BulkAction::back('submission-'.$submission->id)->with('success', 'Decision recorded on #'.$submission->id.' and published to the corrections log. Approved submissions must still be applied to the data/ directory through a pull request.');
    }

    /**
     * One decision for every selected submission: the same notes, the same public note.
     * The selection is the ticked cards, or, with scope=filtered, every submission the
     * Submissions list's filters match (they ride on the query string the form posts to).
     */
    public function decideMany(Request $request): RedirectResponse
    {
        $data = $this->decisionData($request, 'bulk-submissions', [
            'scope' => ['nullable', 'in:filtered'],
            'ids' => ['required_without:scope', 'array', 'max:'.self::BULK_LIMIT],
            'ids.*' => ['integer'],
        ]);
        if (($data['scope'] ?? null) === 'filtered') {
            $matching = SubmissionQuery::query($request);
            $submissions = (clone $matching)->limit(self::BULK_LIMIT)->get();
            $total = (clone $matching)->reorder()->count();
        } else {
            $submissions = ContributorSubmission::whereIn('id', array_unique($data['ids']))->get();
            $total = $submissions->count();
        }
        foreach ($submissions as $submission) {
            $this->recordDecision($submission, $data, $request->user());
        }
        $n = $submissions->count();

        return BulkAction::back('bulk-submissions')->with('success', $n.' '.Str::plural('submission', $n).' marked '.str_replace('_', ' ', $data['decision']).'.'
            .BulkAction::capNote($n, $total, self::BULK_LIMIT)
            .' Approved submissions must still be applied to the data/ directory through a pull request.');
    }

    /**
     * Records one reviewer's verification of many records at once.
     *
     * The selection is the ticked rows, or, with scope=filtered, every record the
     * current filter matches, so "everything pending in this jurisdiction" is one act
     * once the reviewer has actually read it. Review status and confidence each default
     * to "keep current": only a field the reviewer chose is changed, so re-checking a
     * mixed selection never flattens its confidence levels.
     */
    public function verifyMany(Request $request, string $type): RedirectResponse
    {
        abort_unless(ReviewableTypes::has($type), 404);
        $anchor = 'bulk-'.$type;
        $validator = Validator::make($request->all(), [
            'slugs' => ['required_without:scope', 'array', 'max:'.self::BULK_LIMIT],
            'slugs.*' => ['string', 'max:190'],
            'scope' => ['nullable', 'in:filtered'],
            'review_status' => ['required', 'in:'.implode(',', [self::KEEP, ...ReviewableTypes::REVIEW_STATUSES])],
            'confidence_level' => ['required', 'in:'.implode(',', [self::KEEP, ...ReviewableTypes::CONFIDENCE_LEVELS])],
            'source_opened' => ['exclude_unless:review_status,verified', 'required_if:review_status,verified', 'accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], self::attestationMessages('every selected record'));
        $validator->after(function ($v) use ($request) {
            if ($request->input('review_status') === self::KEEP && $request->input('confidence_level') === self::KEEP) {
                $v->errors()->add('review_status', 'Nothing to change: choose a review status, a confidence level, or both. "Keep current" on both leaves every record as it is.');
            }
        });
        if ($validator->fails()) {
            return BulkAction::back($anchor)->withErrors($validator)->withInput();
        }
        $data = $validator->validated();
        $keepStatus = $data['review_status'] === self::KEEP;
        $keepConfidence = $data['confidence_level'] === self::KEEP;

        if ($data['review_status'] === 'verified' && ! $this->isPublishedReviewer($request->user()->name)) {
            return BulkAction::back($anchor)->withErrors(['source_opened' => $this->rosterError($request->user()->name)])->withInput();
        }

        $recorded = 0;
        $skipped = [];
        [$models, $total] = $this->selection($request, $type, $data);
        foreach ($models as $model) {
            if (! $keepStatus && $this->lacksRequiredSource($type, $model, $data['review_status'])) {
                $skipped[] = $model->slug;

                continue;
            }
            $this->record($type, $model, $data, $request->user());
            $recorded++;
        }
        ContentCache::flush();

        $message = $recorded.' '.ReviewableTypes::label($type, $recorded !== 1)
            .($keepStatus ? ' updated · review status kept' : ' marked '.str_replace('_', ' ', $data['review_status']))
            .($keepConfidence ? ' · confidence kept for '.$recorded : ' · confidence '.$data['confidence_level'])
            .' · by '.$request->user()->name.'.'
            .BulkAction::capNote(count($models), $total, self::BULK_LIMIT)
            .' Run `php artisan policy:export-verifications` and open a pull request to write it into data/.';
        if ($skipped !== []) {
            $message .= ' Skipped '.count($skipped).' with no official source URL ('.implode(', ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? ', …' : '').'): add the source in data/ first.';
        }

        return BulkAction::back($anchor)->with('success', $message);
    }

    /** Records a human verification (reviewer opened the official source) and applies it to the live row. */
    public function verify(Request $request, string $type, string $slug): RedirectResponse
    {
        $model = $this->reviewable($type, $slug);
        $data = $request->validate([
            'review_status' => ['required', 'in:'.implode(',', ReviewableTypes::REVIEW_STATUSES)],
            'confidence_level' => ['required', 'in:'.implode(',', ReviewableTypes::CONFIDENCE_LEVELS)],
            'source_opened' => ['exclude_unless:review_status,verified', 'required_if:review_status,verified', 'accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], self::attestationMessages('this record'));

        // A verification is only worth something if the person who made it is named and
        // has published what they are interested in. The data validator refuses a record
        // verified by anyone outside the roster, so refusing it here too keeps the admin
        // from writing a decision that could never be exported.
        if ($data['review_status'] === 'verified' && ! $this->isPublishedReviewer($request->user()->name)) {
            return back()->withErrors(['source_opened' => $this->rosterError($request->user()->name)])->withInput();
        }

        if ($this->lacksRequiredSource($type, $model, $data['review_status'])) {
            return back()->withErrors(['review_status' => $this->sourceError($type)])->withInput();
        }

        $this->record($type, $model, $data, $request->user());
        ContentCache::flush();

        return BulkAction::back('rec-'.$slug)->with('success', ucfirst($type).' '.$slug.' marked '.$data['review_status'].' ('.$data['confidence_level'].'). Run `php artisan policy:export-verifications` and open a pull request to write it into data/.');
    }

    public function publish(Request $request, string $type, string $slug): RedirectResponse
    {
        $model = $this->reviewable($type, $slug);
        $publish = $request->boolean('publish');
        $this->setPublished($type, $model, $publish);
        ContentCache::flush();

        return BulkAction::back('rec-'.$model->slug)->with('success', ($publish ? 'Published ' : 'Unpublished ').$model->slug.'. Remember to mirror the change in data/ (published: '.($publish ? 'true' : 'false').').');
    }

    /** The publish switch for a selection. Unpublishing hides records from the site, API and sitemaps at once. */
    public function publishMany(Request $request, string $type): RedirectResponse
    {
        abort_unless(ReviewableTypes::has($type), 404);
        $validator = Validator::make($request->all(), [
            'slugs' => ['required_without:scope', 'array', 'max:'.self::BULK_LIMIT],
            'slugs.*' => ['string', 'max:190'],
            'scope' => ['nullable', 'in:filtered'],
            'publish' => ['required', 'boolean'],
        ]);
        if ($validator->fails()) {
            return BulkAction::back('bulk-'.$type)->withErrors($validator)->withInput();
        }
        $data = $validator->validated();
        $publish = (bool) $data['publish'];
        [$models, $total] = $this->selection($request, $type, $data);
        foreach ($models as $model) {
            $this->setPublished($type, $model, $publish);
        }
        $n = count($models);
        ContentCache::flush();

        return BulkAction::back('bulk-'.$type)->with('success', ($publish ? 'Published ' : 'Unpublished ').$n.' '.ReviewableTypes::label($type, $n !== 1).'.'
            .BulkAction::capNote($n, $total, self::BULK_LIMIT)
            .' Remember to mirror the change in data/ (published: '.($publish ? 'true' : 'false').').');
    }

    /** @return array<string, string> the attestation messages, in plain words */
    private static function attestationMessages(string $what): array
    {
        return [
            'source_opened.required_if' => 'To mark '.$what.' verified, tick the box saying you opened the official source. A record counts as verified only once a person has read its source.',
            'source_opened.accepted' => 'Confirm that you opened the official source of '.$what.' before marking it verified.',
        ];
    }

    /**
     * Enforcement events waiting for review, read-only.
     *
     * They are items inside their instrument's YAML file, the importer deletes and
     * recreates them on every run, and an event need not carry a slug in the file, so a
     * decision stored here could neither be re-applied after an import nor written back
     * to the right list item. They are listed so the work is visible; the decision is
     * made in the policy file by pull request.
     *
     * @return array{total: int, events: Collection<int, EnforcementEvent>, files: Collection<string, ?string>}
     */
    private function pendingEnforcementEvents(): array
    {
        $query = EnforcementEvent::where('review_status', 'pending_review');
        $events = (clone $query)->with(['policyInstrument', 'jurisdiction'])->orderByDesc('occurred_on')->orderBy('id')->limit(self::PER_PAGE)->get();
        $files = $events->pluck('policyInstrument.slug')->filter()->unique()
            ->mapWithKeys(fn (string $slug) => [$slug => ($f = ReviewableTypes::file('policy', $slug)) ? Str::after($f, base_path().'/') : null]);

        return ['total' => $query->count(), 'events' => $events, 'files' => $files];
    }

    // ---- selection and filters -------------------------------------------------

    /** @return array{q: string, review: ?string, published: ?string} */
    /** Days after which a verification is stale: the setting, never the hard-coded default alone. */
    public static function staleAfter(): int
    {
        return max(1, (int) config('aipolicytracker.stale_after_days', 180));
    }

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
        if ($filters['review'] === 'stale') {
            $query->where(fn (Builder $q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays(self::staleAfter())));
        } elseif ($filters['review']) {
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
     * current filter matches when the reviewer asked for the whole filtered set, up to
     * BULK_LIMIT. The second value is how many the selection matched in all, so the
     * message can say when the cap stopped it short.
     *
     * @return array{0: list<Model>, 1: int}
     */
    private function selection(Request $request, string $type, array $data): array
    {
        if (($data['scope'] ?? null) === 'filtered') {
            $query = $this->filtered($type, $this->filters($request));

            return [(clone $query)->limit(self::BULK_LIMIT)->get()->all(), (clone $query)->reorder()->count()];
        }
        $models = [];
        foreach (array_unique($data['slugs'] ?? []) as $slug) {
            if ($model = ReviewableTypes::find($type, $slug)) {
                $models[] = $model;
            }
        }

        return [$models, count($models)];
    }

    /** One display row, the same shape for every kind so the table is one template. */
    private function row(string $type, Model $m): array
    {
        $staleAfter = self::staleAfter();
        $context = match ($type) {
            'policy' => $m->jurisdiction?->name ?? '—',
            'jurisdiction' => $m->region ?: '—',
            'control' => $m->kindLabel().' · '.$m->obligations_count.' '.Str::plural('duty', $m->obligations_count),
            'change' => $m->occurred_on?->format('j M Y').' · '.($m->jurisdiction?->name ?? '—'),
            'transition_measure' => $m->typeLabel().' · '.$m->statusLabel(),
            'implementation_measure' => $m->kindLabel().' · '.$m->statusLabel(),
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

    /**
     * One stored verification, applied to the live row so the site reflects it before the export.
     *
     * A field sent as KEEP keeps the record's current value. Changing only the confidence is
     * not a new verification, so a kept "verified" also keeps its date and the reviewer who
     * signed it; the change is still stored under the acting account's user id.
     */
    private function record(string $type, Model $model, array $data, Authenticatable $user): void
    {
        $keepStatus = $data['review_status'] === self::KEEP;
        $status = $keepStatus ? $model->review_status : $data['review_status'];
        $confidence = $data['confidence_level'] === self::KEEP ? $model->confidence_level : $data['confidence_level'];
        $lastVerified = $keepStatus ? $model->last_verified_at?->toDateString() : ($status === 'verified' ? now()->toDateString() : null);
        $reviewedBy = $keepStatus && $status === 'verified' ? ($model->reviewed_by ?? $user->name) : $user->name;
        $verification = RecordVerification::updateOrCreate(['record_type' => $type, 'record_slug' => $model->slug], [
            'review_status' => $status, 'confidence_level' => $confidence, 'last_verified_at' => $lastVerified,
            'reviewed_by' => $reviewedBy, 'source_checked_url' => $model->official_source_url ?? null, 'notes' => $data['notes'] ?? null, 'user_id' => $user->getAuthIdentifier(), 'exported' => false,
        ]);
        $model->forceFill(['review_status' => $verification->review_status, 'confidence_level' => $verification->confidence_level, 'last_verified_at' => $verification->last_verified_at, 'reviewed_by' => $verification->reviewed_by])->save();
    }

    private function setPublished(string $type, Model $model, bool $publish): void
    {
        $was = $model->published_at;
        // Publishing an already-published record keeps the date it first went public.
        $model->forceFill(['published_at' => $publish ? ($was ?? now()) : null])->save();
        // An instrument's duties follow it: unpublishing hides them all, because a hidden
        // instrument with visible obligations would point readers at a record that is not
        // there. Publishing brings back only what went down with it (a policy that was
        // hidden), so an obligation left unpublished on purpose under a live policy stays so.
        if ($type === 'policy') {
            if (! $publish) {
                $model->obligations()->update(['published_at' => null]);
            } elseif ($was === null) {
                $model->obligations()->whereNull('published_at')->update(['published_at' => now()]);
            }
        }
    }

    /** Validated decision fields; on failure the page comes back at the form that was sent. */
    private function decisionData(Request $request, string $anchor, array $extra = []): array
    {
        $validator = Validator::make($request->all(), $extra + [
            'decision' => ['required', 'in:approved,rejected,needs_information'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Written deliberately for /corrections. `notes` is the internal record and is
            // never published, so a reviewer who wants to say something publicly says it here.
            'public_note' => ['nullable', 'string', 'max:500'],
        ], ['decision.required' => 'Choose a decision before recording it.', 'ids.required_without' => 'Tick at least one submission, or choose all matching.']);
        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(BulkAction::back($anchor)->getTargetUrl());
        }

        return $validator->validated();
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

    /** See ReviewableTypes::needsSource: a decision the data check would refuse is not stored. */
    private function lacksRequiredSource(string $type, Model $model, string $reviewStatus): bool
    {
        return ReviewableTypes::needsSource($type, $reviewStatus) && blank($model->official_source_url ?? null);
    }

    private function sourceError(string $type): string
    {
        return 'This '.Str::lower(ReviewableTypes::label($type)).' has no official source URL, so the data check would refuse the decision. Add the source in data/ through a pull request first.';
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
