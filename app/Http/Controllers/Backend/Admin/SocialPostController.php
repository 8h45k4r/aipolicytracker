<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\SocialPost;
use App\Services\Social\ChangePost;
use App\Services\Social\XClient;
use App\Services\Social\XPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Posts to X: what will go out next, what went out, and what X refused.
 * A post is public speech by the project, so it sits with publishing
 * (records.publish). The keys and the on/off switch stay with the owner on
 * the settings page. Every write is audited by the admin.audit middleware.
 */
class SocialPostController extends Controller
{
    public function index(Request $request, XPublisher $publisher, XClient $client): View
    {
        $status = $request->query('status');
        $status = is_string($status) && array_key_exists($status, SocialPost::STATUSES) ? $status : null;
        $posts = SocialPost::with(['changeEvent.jurisdiction', 'approver'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'draft' then 0 when 'queued' then 1 when 'failed' then 2 else 3 end")
            ->orderByDesc('created_at')
            ->paginate(25)->withQueryString();
        $counts = SocialPost::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('backend.admin.social', [
            'posts' => $posts,
            'status' => $status,
            'counts' => $counts,
            'pending' => $publisher->eligible(),
            'enabled' => (bool) config('social.x.enabled'),
            'configured' => $client->configured(),
            'mode' => config('social.x.mode') === 'review' ? 'review' : 'auto',
            'cap' => (int) config('social.x.monthly_cap'),
            'thisMonth' => $publisher->postedThisMonth(),
            'recent' => ChangeEvent::published()->where('review_status', 'verified')->orderByDesc('occurred_on')->limit(40)->get(['id', 'slug', 'title', 'occurred_on']),
        ]);
    }

    /** Queue posts for new changes now instead of waiting for the next scheduled run. */
    public function queue(XPublisher $publisher): RedirectResponse
    {
        $made = $publisher->queue();

        return back()->with('success', $made ? "{$made} new ".($made === 1 ? 'post' : 'posts').' added.' : 'No new verified change needs a post.');
    }

    /** A post for a change the automatic rules passed over, an older one for instance. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['change' => ['required', 'string', Rule::exists('change_events', 'slug')->whereNotNull('published_at')]]);
        $change = ChangeEvent::where('slug', $data['change'])->firstOrFail();
        $post = SocialPost::firstOrCreate(['change_event_id' => $change->id, 'network' => 'x'], ['status' => 'draft', 'text' => ChangePost::compose($change)]);

        return back()->with($post->wasRecentlyCreated ? 'success' : 'error', $post->wasRecentlyCreated ? 'Draft added. Read it, then approve it to queue it.' : 'That change already has a post ('.SocialPost::STATUSES[$post->status].').');
    }

    public function update(Request $request, SocialPost $post): RedirectResponse
    {
        abort_unless($post->editable(), 422, 'A post that was sent or skipped cannot be edited.');
        $data = $request->validate(['text' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail) {
            if (ChangePost::weight((string) $value) > ChangePost::LIMIT) {
                $fail('X allows 280 characters, counting each link as 23. This is '.ChangePost::weight((string) $value).'.');
            }
        }]]);
        $post->update(['text' => trim($data['text'])]);

        return back()->with('success', 'Post text saved.');
    }

    public function approve(Request $request, SocialPost $post): RedirectResponse
    {
        abort_unless(in_array($post->status, ['draft', 'failed', 'skipped'], true), 422, 'Only a draft, failed or skipped post can be queued.');
        $post->update(['status' => 'queued', 'approved_by' => $request->user()->id, 'error' => null, 'attempts' => 0]);

        return back()->with('success', 'Queued. It goes out on the next run'.(config('social.x.enabled') ? ', within 30 minutes.' : ' once posting is turned on.'));
    }

    public function approveAll(Request $request): RedirectResponse
    {
        $n = SocialPost::where('status', 'draft')->update(['status' => 'queued', 'approved_by' => $request->user()->id]);

        return back()->with('success', $n ? "{$n} ".($n === 1 ? 'draft' : 'drafts').' queued.' : 'No drafts to approve.');
    }

    /** Send one post now, within the same switch and cap as the schedule. */
    public function send(Request $request, SocialPost $post, XPublisher $publisher, XClient $client): RedirectResponse
    {
        abort_unless(in_array($post->status, ['draft', 'queued', 'failed'], true), 422, 'This post was already sent or skipped.');
        $held = match (true) {
            ! config('social.x.enabled') => 'Posting to X is off. Turn it on under Settings → Live switches.',
            ! $client->configured() => 'The X keys are not set. Add them under Settings → Posting to X.',
            $publisher->capReached() => 'This month\'s cap of '.(int) config('social.x.monthly_cap').' posts is reached.',
            default => null,
        };
        if ($held) {
            return back()->with('error', $held);
        }
        $post->update(['approved_by' => $post->approved_by ?? $request->user()->id]);

        return $publisher->send($post)
            ? back()->with('success', 'Posted to X.')
            : back()->with('error', 'X did not take the post: '.$post->fresh()->error);
    }

    public function skip(SocialPost $post): RedirectResponse
    {
        abort_unless(in_array($post->status, ['draft', 'queued', 'failed'], true), 422, 'This post was already sent.');
        $post->update(['status' => 'skipped']);

        return back()->with('success', 'Skipped. It will not be posted unless you queue it again.');
    }
}
