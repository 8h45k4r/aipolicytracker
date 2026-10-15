<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyInstrument;
use App\Models\SourceCheck;
use App\Services\ChangeDesk\Freshness;
use App\Services\ChangeDesk\SourceQueue;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The change desk: the weekly routine that keeps the record current. Open each due source,
 * log what you found, and log any change in data/changes. Reviewers and editors
 * (records.verify). Every check is audited by the admin.audit middleware.
 */
class ChangeDeskController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $rows = SourceQueue::rows();
        $filter = $request->query('show') === 'all' ? 'all' : 'due';
        $shown = $filter === 'all' ? $rows : $rows->where('due', true)->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $recent = SourceCheck::with('user:id,name')->latest('checked_at')->limit(10)->get();
        $titles = PolicyInstrument::whereIn('slug', $recent->pluck('record_slug')->unique())->get(['slug', 'title', 'short_title'])
            ->mapWithKeys(fn ($p) => [$p->slug => $p->short_title ?: $p->title]);

        return view('backend.admin.change-desk', [
            'rows' => (new LengthAwarePaginator($shown->forPage($page, self::PER_PAGE)->values(), $shown->count(), self::PER_PAGE, $page, ['path' => $request->url()]))->withQueryString(),
            'filter' => $filter,
            'dueCount' => $rows->where('due', true)->count(),
            'total' => $rows->count(),
            'checkedThisWeek' => SourceCheck::where('checked_at', '>=', now()->startOfWeek(CarbonInterface::MONDAY))->count(),
            'deepen' => SourceQueue::deepenNext(10, $rows),
            'freshness' => Freshness::summary(Freshness::measured()),
            'targetDays' => Freshness::targetDays(),
            'since' => Freshness::since(),
            'recent' => $recent,
            'titles' => $titles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'policy' => ['required', 'string', Rule::exists('policy_instruments', 'slug')->whereNotNull('published_at')],
            'outcome' => ['required', Rule::in(array_keys(SourceCheck::OUTCOMES))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        SourceCheck::create(['record_type' => 'policy', 'record_slug' => $data['policy'], 'outcome' => $data['outcome'], 'note' => $data['note'] ?? null, 'user_id' => $request->user()->id, 'checked_at' => now()]);
        $policy = PolicyInstrument::where('slug', $data['policy'])->first(['short_title', 'title']);
        $title = $policy?->short_title ?: ($policy?->title ?: $data['policy']);
        $next = $data['outcome'] === 'change_logged' ? ' Add the change to data/changes so it reaches the change log, alerts and the digest.' : '';

        return back()->with('success', $title.': '.mb_strtolower(SourceCheck::OUTCOMES[$data['outcome']]).' recorded.'.$next);
    }
}
