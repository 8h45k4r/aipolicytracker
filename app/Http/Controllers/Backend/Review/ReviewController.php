<?php

namespace App\Http\Controllers\Backend\Review;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\ContributorSubmission;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Models\ReviewerDecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Admin-only review queue and publishing controls. Data edits themselves happen
 * in the data/ directory through pull requests; this area handles triage of
 * community submissions and the publish/unpublish switch for records.
 */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), SubmissionStatus::values(), true) ? $request->query('status') : 'pending_review';
        $submissions = ContributorSubmission::where('status', $status)->with('decisions.reviewer')->orderByDesc('created_at')->paginate(25)->withQueryString();
        $counts = ContributorSubmission::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $policies = PolicyInstrument::with('jurisdiction')->orderByRaw("CASE review_status WHEN 'verified' THEN 1 ELSE 0 END")->orderBy('confidence_level')->orderBy('title')->get();
        $pendingExport = \App\Models\RecordVerification::where('exported', false)->count();
        $jurisdictions = Jurisdiction::orderBy('name')->get();

        return view('backend.review.index', compact('pendingExport', 'submissions', 'status', 'counts', 'policies', 'jurisdictions'));
    }

    public function decide(Request $request, ContributorSubmission $submission): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected,needs_information'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        ReviewerDecision::create([
            'contributor_submission_id' => $submission->id,
            'reviewer_user_id' => $request->user()->id,
            'decision' => $data['decision'],
            'notes' => $data['notes'] ?? null,
            'decided_at' => now(),
        ]);
        $submission->update(['status' => $data['decision']]);

        return back()->with('success', 'Decision recorded. Approved submissions must still be applied to the data/ directory through a pull request.');
    }

    /** Records a human verification (reviewer opened the official source) and applies it to the live row. */
    public function verify(Request $request, string $type, string $slug): RedirectResponse
    {
        $model = match ($type) {
            'policy' => PolicyInstrument::where('slug', $slug)->firstOrFail(),
            'jurisdiction' => Jurisdiction::where('slug', $slug)->firstOrFail(),
            default => abort(404),
        };
        $data = $request->validate([
            'review_status' => ['required', 'in:verified,pending_review,needs_update'],
            'confidence_level' => ['required', 'in:high,medium,low,unavailable'],
            'source_opened' => ['required_if:review_status,verified', 'accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['source_opened.accepted' => 'Confirm that you opened the official source before marking a record verified.']);
        $verified = $data['review_status'] === 'verified';
        $verification = \App\Models\RecordVerification::updateOrCreate(['record_type' => $type, 'record_slug' => $slug], [
            'review_status' => $data['review_status'], 'confidence_level' => $data['confidence_level'], 'last_verified_at' => $verified ? now()->toDateString() : null,
            'reviewed_by' => $request->user()->name, 'source_checked_url' => $model->official_source_url, 'notes' => $data['notes'] ?? null, 'user_id' => $request->user()->id, 'exported' => false,
        ]);
        $model->forceFill(['review_status' => $verification->review_status, 'confidence_level' => $verification->confidence_level, 'last_verified_at' => $verification->last_verified_at, 'reviewed_by' => $verification->reviewed_by])->save();
        Cache::flush();

        return back()->with('success', ucfirst($type).' '.$slug.' marked '.$data['review_status'].' ('.$data['confidence_level'].'). Run `php artisan policy:export-verifications` and open a pull request to write it into data/.');
    }

    public function publish(Request $request, string $type, string $slug): RedirectResponse
    {
        $model = match ($type) {
            'policy' => PolicyInstrument::where('slug', $slug)->firstOrFail(),
            'jurisdiction' => Jurisdiction::where('slug', $slug)->firstOrFail(),
            default => abort(404),
        };
        $publish = $request->boolean('publish');
        $model->update(['published_at' => $publish ? now() : null]);
        if ($type === 'policy') {
            $model->obligations()->update(['published_at' => $publish ? now() : null]);
        }
        Cache::flush();

        return back()->with('success', ($publish ? 'Published ' : 'Unpublished ').$model->slug.'. Remember to mirror the change in data/ (published: '.($publish ? 'true' : 'false').').');
    }
}
