<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyInstrument;
use App\Models\SourceCheck;
use App\Services\ChangeDesk\Freshness;
use App\Services\ChangeDesk\SourceQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The change desk: the weekly routine that keeps the record current. Open each due source,
 * log what you found, and log any change in data/changes. Reviewers and editors
 * (records.verify). Every check is audited by the admin.audit middleware.
 */
class ChangeDeskController extends Controller
{
    public function index(Request $request): View
    {
        $rows = SourceQueue::rows();
        $filter = $request->query('show') === 'all' ? 'all' : 'due';

        return view('backend.admin.change-desk', [
            'rows' => $filter === 'all' ? $rows : $rows->where('due', true)->values(),
            'filter' => $filter,
            'dueCount' => $rows->where('due', true)->count(),
            'total' => $rows->count(),
            'checkedThisWeek' => SourceCheck::where('checked_at', '>=', now()->startOfWeek())->count(),
            'deepen' => SourceQueue::deepenNext(),
            'freshness' => Freshness::summary(Freshness::measured()),
            'targetDays' => Freshness::targetDays(),
            'since' => Freshness::since(),
            'recent' => SourceCheck::with('user:id,name')->latest('checked_at')->limit(10)->get(),
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
        $title = PolicyInstrument::where('slug', $data['policy'])->value('short_title') ?: $data['policy'];
        $next = $data['outcome'] === 'change_logged' ? ' Add the change to data/changes so it reaches the change log, alerts and the digest.' : '';

        return back()->with('success', $title.': '.mb_strtolower(SourceCheck::OUTCOMES[$data['outcome']]).' recorded.'.$next);
    }
}
