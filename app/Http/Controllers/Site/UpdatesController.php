<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Services\Changes\Significance;
use App\Services\Changes\UpdatesSummary;
use App\Support\PageTitle;
use App\Support\Seo;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The updates hub: the change log arranged the way people search for it.
 *
 * "ai policy updates" and "ai policy updates today" were the site's most-shown
 * queries with no clicks. /changes answers them, but its title and shape say
 * "log", not "what's new". These pages are built from the same records — every
 * item links to its own change page — and add what a searcher wants first: a
 * summary computed from counts, a "last updated" time, the month and day
 * archives, and a page per jurisdiction.
 *
 * A page below the content threshold is served noindex,follow rather than
 * offered as a result: a day with one entry is a real page for the person who
 * followed a link to it and a thin one for an index.
 */
class UpdatesController extends Controller
{
    /** Items a month or jurisdiction page needs before it is offered to an index. */
    public const MIN_INDEXABLE = 3;

    /** A day page is shorter by nature; two entries make it a page about that day. */
    public const MIN_INDEXABLE_DAY = 2;

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $page = $this->query($filters)->orderByDesc('occurred_on')->orderByDesc('id')->paginate(30)->withQueryString();
        $window = $this->query([])->where('occurred_on', '>=', now()->subDays(30)->toDateString())->get();
        // A quiet month should not leave the answer box empty when there is a
        // recent story to tell: fall back to the latest entries on record.
        $summaryFrom = $window->isNotEmpty() ? $window : $this->query([])->orderByDesc('occurred_on')->limit(10)->get();
        $summary = UpdatesSummary::for($summaryFrom, $window->isNotEmpty() ? 'the last 30 days' : 'the most recent entries');
        $indexable = $filters === [] && $page->currentPage() === 1;

        $seo = Seo::make(
            PageTitle::updatesHub(now()),
            'AI policy updates today: every dated, source-backed change in AI law, regulation and guidance worldwide, with what it means in practice. Updated as changes are recorded; RSS and monthly archives.',
            Seo::pagedUrl(route('updates.index'), $filters === [] ? $page->currentPage() : 1),
            $indexable || ($filters === [] && $page->currentPage() > 1)
        )->withBreadcrumbs([['Home', route('home')], ['Updates', route('updates.index')]])
            ->withFeed(route('changes.feed'))
            ->withModified($summary['updated_at'])
            ->withPageType('CollectionPage', [
                'name' => 'AI policy updates',
                'mainEntity' => Seo::itemList($page->getCollection(), fn ($c) => $c->title, fn ($c) => $c->url(), 'Latest AI policy updates'),
            ])
            ->withFaq($this->faq($summary));

        return view('site.updates.page', $this->shared($seo, $page, $summary, $filters) + [
            'heading' => 'AI policy updates',
            'context' => 'hub',
            'feedUrl' => route('changes.feed'),
        ]);
    }

    public function month(string $month): View
    {
        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();
        // The upper bound carries a time: a date column stores midnight, and a
        // string bound of "2025-04-30" would leave that day out of the month.
        $changes = $this->query([])->whereBetween('occurred_on', [$start->toDateString(), $start->copy()->endOfMonth()->format('Y-m-d 23:59:59')])->orderByDesc('occurred_on')->orderByDesc('id')->get();
        abort_if($changes->isEmpty(), 404);
        $summary = UpdatesSummary::for($changes, $start->format('F Y'));
        $months = ChangeEvent::publishedMonths();
        $keys = $months->keys()->values();
        $i = $keys->search($month);

        $seo = Seo::make(
            PageTitle::updatesMonth($start),
            "Every AI policy change recorded for {$start->format('F Y')}: {$changes->count()} dated, source-backed entries across {$summary['jurisdictions']} jurisdictions, with practical impact and official sources.",
            route('updates.month', $month),
            $changes->count() >= self::MIN_INDEXABLE
        )->withBreadcrumbs([['Home', route('home')], ['Updates', route('updates.index')], [$start->format('F Y'), route('updates.month', $month)]])
            ->withFeed(route('changes.feed'))
            ->withModified($summary['updated_at'])
            ->withPageType('CollectionPage', [
                'name' => 'AI policy updates, '.$start->format('F Y'),
                'mainEntity' => Seo::itemList($changes, fn ($c) => $c->title, fn ($c) => $c->url()),
            ]);

        return view('site.updates.page', $this->shared($seo, $changes, $summary, []) + [
            'heading' => 'AI policy updates, '.$start->format('F Y'),
            'context' => 'month',
            'feedUrl' => route('changes.feed'),
            'prev' => $i !== false && isset($keys[$i + 1]) ? ['label' => Carbon::parse($keys[$i + 1].'-01')->format('F Y'), 'url' => route('updates.month', $keys[$i + 1])] : null,
            'next' => $i !== false && $i > 0 ? ['label' => Carbon::parse($keys[$i - 1].'-01')->format('F Y'), 'url' => route('updates.month', $keys[$i - 1])] : null,
        ]);
    }

    public function day(string $day): View
    {
        $date = Carbon::createFromFormat('Y-m-d', $day)->startOfDay();
        $changes = $this->query([])->whereDate('occurred_on', $date->toDateString())->orderByDesc('id')->get();
        abort_if($changes->isEmpty(), 404);
        $summary = UpdatesSummary::for($changes, $date->format('j F Y'));

        $seo = Seo::make(
            PageTitle::updatesDay($date),
            "AI policy changes recorded for {$date->format('j F Y')}: {$changes->count()} dated, source-backed ".($changes->count() === 1 ? 'entry' : 'entries').' with practical impact and official sources.',
            route('updates.day', $day),
            $changes->count() >= self::MIN_INDEXABLE_DAY
        )->withBreadcrumbs([['Home', route('home')], ['Updates', route('updates.index')], [$date->format('F Y'), route('updates.month', $date->format('Y-m'))], [$date->format('j F'), route('updates.day', $day)]])
            ->withFeed(route('changes.feed'))
            ->withModified($summary['updated_at'])
            ->withPageType('CollectionPage', [
                'name' => 'AI policy updates, '.$date->format('j F Y'),
                'mainEntity' => Seo::itemList($changes, fn ($c) => $c->title, fn ($c) => $c->url()),
            ]);

        return view('site.updates.page', $this->shared($seo, $changes, $summary, []) + [
            'heading' => 'AI policy updates, '.$date->format('j F Y'),
            'context' => 'day',
            'feedUrl' => route('changes.feed'),
        ]);
    }

    public function jurisdiction(Jurisdiction $jurisdiction): View
    {
        abort_unless($jurisdiction->published_at, 404);
        $changes = $this->query([])->where('jurisdiction_id', $jurisdiction->id)->orderByDesc('occurred_on')->orderByDesc('id')->get();
        abort_if($changes->isEmpty(), 404);
        $name = $jurisdiction->short_name ?: $jurisdiction->name;
        $summary = UpdatesSummary::for($changes, 'in '.$jurisdiction->nameWithArticle());

        $seo = Seo::make(
            PageTitle::updatesJurisdiction($jurisdiction, now()),
            "AI policy updates for {$jurisdiction->nameWithArticle()}: {$changes->count()} dated, source-backed changes to AI law, regulation and guidance, with practical impact. RSS feed for this jurisdiction.",
            route('updates.jurisdiction', $jurisdiction->slug),
            $jurisdiction->isIndexable() && $changes->count() >= self::MIN_INDEXABLE
        )->withBreadcrumbs([['Home', route('home')], ['Updates', route('updates.index')], [$name, route('updates.jurisdiction', $jurisdiction->slug)]])
            ->withFeed(route('updates.jurisdiction.feed', $jurisdiction->slug))
            ->withModified($summary['updated_at'])
            ->withPageType('CollectionPage', [
                'name' => "AI policy updates: {$jurisdiction->name}",
                'about' => ['@type' => 'Place', 'name' => $jurisdiction->name, 'url' => $jurisdiction->url()],
                'mainEntity' => Seo::itemList($changes, fn ($c) => $c->title, fn ($c) => $c->url()),
            ]);

        return view('site.updates.page', $this->shared($seo, $changes, $summary, []) + [
            'heading' => "AI policy updates: {$jurisdiction->name}",
            'context' => 'jurisdiction',
            'jurisdiction' => $jurisdiction,
            'feedUrl' => route('updates.jurisdiction.feed', $jurisdiction->slug),
        ]);
    }

    /** RSS for one jurisdiction, so a reader can follow one place rather than the world. */
    public function jurisdictionFeed(Jurisdiction $jurisdiction): Response
    {
        abort_unless($jurisdiction->published_at, 404);
        $changes = $this->query([])->where('jurisdiction_id', $jurisdiction->id)->orderByDesc('occurred_on')->orderByDesc('id')->limit(50)->get();
        $xml = view('site.changes.feed', [
            'changes' => $changes,
            'title' => 'AIPolicyTracker: AI policy updates, '.$jurisdiction->name,
            'link' => route('updates.jurisdiction', $jurisdiction->slug),
            'self' => route('updates.jurisdiction.feed', $jurisdiction->slug),
            'description' => "Dated, source-backed AI policy changes in {$jurisdiction->nameWithArticle()}. Informational only; not legal advice.",
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=900']);
    }

    /** @return array{jurisdiction?: string, impact?: string} */
    private function filters(Request $request): array
    {
        $out = [];
        if (preg_match('/^[a-z][a-z0-9-]*$/', (string) $request->query('jurisdiction'))) {
            $out['jurisdiction'] = $request->query('jurisdiction');
        }
        if (in_array($request->query('impact'), ['urgent', 'high', 'routine'], true)) {
            $out['impact'] = $request->query('impact');
        }

        return $out;
    }

    /** @param array{jurisdiction?: string, impact?: string} $filters */
    private function query(array $filters): Builder
    {
        return ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])
            ->when($filters['jurisdiction'] ?? null, fn ($q, $slug) => $q->whereHas('jurisdiction', fn ($j) => $j->where('slug', $slug)))
            ->when($filters['impact'] ?? null, fn ($q, $impact) => $q->where('impact_level', $impact));
    }

    /** @return array<string,mixed> */
    private function shared(Seo $seo, $changes, array $summary, array $filters): array
    {
        return [
            'seo' => $seo,
            'changes' => $changes,
            'summary' => $summary,
            'filters' => $filters,
            'months' => ChangeEvent::publishedMonths(),
            'jurisdictions' => Jurisdiction::published()->orderBy('name')->get(['slug', 'name']),
            'scoreOf' => fn (ChangeEvent $c) => Significance::forChange($c),
        ];
    }

    /**
     * Questions a searcher of "ai policy updates" actually has, answered from
     * the data on the page. A question whose answer would be empty is not asked.
     *
     * @return list<array{question:string, answer:string}>
     */
    private function faq(array $summary): array
    {
        $items = [];
        if ($summary['latest_on']) {
            $items[] = ['question' => 'When was this page last updated?', 'answer' => 'The most recent change on record occurred on '.$summary['latest_on']->format('j F Y').'. The page is generated from the records, so it changes the moment a new entry is logged; the "Last updated" time above is the last edit to any record shown.'];
        }
        $items[] = ['question' => 'Where do these updates come from?', 'answer' => 'Every entry is written from an official source — a journal, a regulator, a legislature — and links to it. Nothing here is a press summary of a press summary. Entries record what changed, the status after the change and what it means in practice.'];
        $items[] = ['question' => 'How is "top story" decided?', 'answer' => 'By a published rule, not an editor\'s mood: impact level, whether the instrument is binding, whether it entered into force, whether a named reviewer confirmed the record, and how recent it is, added up and shown as a score out of 100.'];
        $items[] = ['question' => 'Can I follow updates for one country?', 'answer' => 'Yes. Every jurisdiction with recorded changes has its own updates page and RSS feed, and the weekly digest can be limited to the jurisdictions you choose.'];

        return $items;
    }
}
