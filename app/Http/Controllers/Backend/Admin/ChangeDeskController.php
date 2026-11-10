<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyInstrument;
use App\Models\SourceCheck;
use App\Services\ChangeDesk\Freshness;
use App\Services\ChangeDesk\SourceQueue;
use App\Support\Admin\BulkAction;
use App\Support\Admin\ListFilters;
use App\Support\Csv;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $filters = ListFilters::from($request, []);
        $shown = $this->narrow($filter === 'all' ? $rows : $rows->where('due', true)->values(), $request, $filters);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $recent = SourceCheck::with('user:id,name')->latest('checked_at')->limit(10)->get();
        $titles = PolicyInstrument::whereIn('slug', $recent->pluck('record_slug')->unique())->get(['slug', 'title', 'short_title'])
            ->mapWithKeys(fn ($p) => [$p->slug => $p->short_title ?: $p->title]);

        return view('backend.admin.change-desk', [
            'rows' => (new LengthAwarePaginator($shown->forPage($page, self::PER_PAGE)->values(), $shown->count(), self::PER_PAGE, $page, ['path' => $request->url()]))->withQueryString(),
            'filter' => $filter,
            'filters' => $filters,
            'shownCount' => $shown->count(),
            'jurisdictions' => $rows->map(fn ($r) => $r['policy']->jurisdiction)->filter()->unique('slug')->sortBy('name')->values(),
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

    /**
     * The sources shown, as CSV: the same view (due or all) and the same filters, every page.
     */
    public function export(Request $request): StreamedResponse
    {
        $rows = SourceQueue::rows();
        $shown = $this->narrow($request->query('show') === 'all' ? $rows : $rows->where('due', true)->values(), $request, ListFilters::from($request, []));

        return response()->streamDownload(function () use ($shown) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['aipt_id', 'instrument', 'jurisdiction', 'binding', 'obligations', 'watchers', 'cadence_days', 'last_checked', 'last_outcome', 'due', 'overdue_days', 'official_source_url']);
            foreach ($shown as $r) {
                $p = $r['policy'];
                fputcsv($out, Csv::row([$p->aipt_id, $p->short_title ?: $p->title, $p->jurisdiction?->name, $p->is_binding ? 'yes' : 'no', $p->obligations_count, $r['watchers'], $r['cadence'], $r['last_at']?->format('Y-m-d H:i:s'), $r['last_outcome'], $r['due'] ? 'yes' : 'no', $r['overdue_days'], $p->official_source_url]));
            }
            fclose($out);
        }, 'change-desk-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Search (title, short title, permanent id, jurisdiction), jurisdiction and binding. */
    private function narrow(Collection $rows, Request $request, ListFilters $filters): Collection
    {
        $q = mb_strtolower(trim((string) $filters->q));
        $place = (string) $request->query('jurisdiction', '');
        $binding = $request->query('binding');

        return $rows->filter(function ($r) use ($q, $place, $binding) {
            $p = $r['policy'];
            if ($q !== '' && ! str_contains(mb_strtolower(implode(' ', [$p->title, $p->short_title, $p->aipt_id, $p->slug, $p->jurisdiction?->name])), $q)) {
                return false;
            }
            if ($place !== '' && $p->jurisdiction?->slug !== $place) {
                return false;
            }

            return ! in_array($binding, ['yes', 'no'], true) || (bool) $p->is_binding === ($binding === 'yes');
        })->values();
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

    private const BULK_LIMIT = 50;

    public function bulkCheck(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slugs' => ['required', 'array', 'max:'.self::BULK_LIMIT],
            'slugs.*' => ['required', 'string', Rule::exists('policy_instruments', 'slug')->whereNotNull('published_at')],
            'outcome' => ['required', Rule::in(array_keys(SourceCheck::OUTCOMES))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $applied = 0;
        foreach ($data['slugs'] as $slug) {
            SourceCheck::create([
                'record_type' => 'policy',
                'record_slug' => $slug,
                'outcome' => $data['outcome'],
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
                'checked_at' => now(),
            ]);
            $applied++;
        }

        return BulkAction::back('desk-table')
            ->with('success', $applied.' '.str('check')->plural($applied).' recorded as "'.mb_strtolower(SourceCheck::OUTCOMES[$data['outcome']]).'".'
                .BulkAction::capNote($applied, count($data['slugs']), self::BULK_LIMIT));
    }
}
