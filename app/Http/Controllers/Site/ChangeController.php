<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Services\PolicyData\PolicyCatalog;
use App\Services\Records\AnswerBox;
use App\Support\Admin\CsvStream;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChangeController extends Controller
{
    /** Sort choices for the change log; the default is newest first, as the log has always been. */
    public const SORTS = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'impact' => 'Impact, urgent first', 'title' => 'Title A to Z'];

    public function index(Request $request, PolicyCatalog $catalog): View
    {
        $filters = $catalog->filtersFromRequest($request);
        $impact = in_array($request->query('impact'), ['urgent', 'high', 'routine'], true) ? $request->query('impact') : null;
        $sort = $this->sort($request);

        $changes = $this->query($filters, $impact, $sort)->paginate(25)->withQueryString();
        // A page past the last is not an empty page of results; it does not exist.
        abort_if($changes->currentPage() > max(1, $changes->lastPage()), 404);
        $urgent = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->whereIn('impact_level', ['urgent', 'high'])->orderByDesc('occurred_on')->limit(5)->get();
        $years = ChangeEvent::publishedYears();
        $indexable = empty($filters['q']) && $impact === null && empty($filters['jurisdiction']);

        $seo = Seo::make(
            PageTitle::listing(['AI policy change log: dated, source-backed updates', 'AI policy change log'], $changes->currentPage()),
            'Chronological log of AI policy changes across jurisdictions: what changed, practical impact, status after the change, official source and verification date. RSS available.',
            Seo::pagedUrl(route('changes.index'), $indexable ? $changes->currentPage() : 1),
            $indexable
        )->withBreadcrumbs([['Home', route('home')], ['Changes', route('changes.index')]])
            ->withFeed(route('changes.feed'))
            ->withPageType('CollectionPage', [
                'name' => 'AI policy change log',
                'mainEntity' => Seo::itemList($changes->getCollection(), fn ($c) => $c->title, fn ($c) => $c->slug ? $c->url() : null, 'Recorded AI policy changes'),
            ])
            // The log is also a feed. Saying so lets a machine follow it instead of
            // re-reading the page to find out whether anything moved.
            ->withJsonLd(Seo::dataset(
                'AI policy change log',
                'Dated, source-backed record of AI policy changes across jurisdictions, with the practical impact and the status after each change.',
                route('changes.index'),
                ['application/rss+xml' => route('changes.feed')],
            ));

        return view('site.changes.index', [
            'seo' => $seo, 'changes' => $changes, 'urgent' => $urgent, 'years' => $years, 'filters' => $filters, 'impact' => $impact, 'sort' => $sort,
            'jurisdictions' => Jurisdiction::published()->orderBy('name')->get(['slug', 'name']),
        ]);
    }

    /** The change log as a CSV download, with the same filters and order as the listing on screen. */
    public function export(Request $request, PolicyCatalog $catalog): StreamedResponse
    {
        $filters = $catalog->filtersFromRequest($request);
        $impact = in_array($request->query('impact'), ['urgent', 'high', 'routine'], true) ? $request->query('impact') : null;

        return CsvStream::from($this->query($filters, $impact, $this->sort($request)), 'ai-policy-changes',
            ['occurred_on', 'title', 'jurisdiction', 'impact_level', 'status_after', 'what_changed', 'official_source_url', 'url'],
            fn (ChangeEvent $c) => [$c->occurred_on?->toDateString(), $c->title, $c->jurisdiction?->name, $c->impact_level, $c->status_after, $c->what_changed, $c->official_source_url, $c->url()]);
    }

    private function sort(Request $request): string
    {
        $sort = $request->query('sort');

        return is_string($sort) && array_key_exists($sort, self::SORTS) ? $sort : 'newest';
    }

    /** The published change log for the given filters, in the given order; every order ends on the id. */
    private function query(array $filters, ?string $impact, string $sort): Builder
    {
        $query = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument']);
        if (! empty($filters['q'])) {
            $query->search($filters['q']);
        }
        if (! empty($filters['jurisdiction'])) {
            $query->whereHas('jurisdiction', fn ($j) => $j->where('slug', $filters['jurisdiction']));
        }
        if ($impact) {
            $query->where('impact_level', $impact);
        }

        return match ($sort) {
            'oldest' => $query->orderBy('occurred_on')->orderBy('id'),
            'impact' => $query->orderByRaw("CASE impact_level WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'routine' THEN 2 ELSE 3 END")->orderByDesc('occurred_on')->orderByDesc('id'),
            'title' => $query->orderByRaw('LOWER(title)')->orderBy('id'),
            default => $query->orderByDesc('occurred_on')->orderByDesc('id'),
        };
    }

    /**
     * A single change as a page. The record is short, so the page says what
     * changed, what it means, where it came from and where it sits, and then
     * points at the instrument, the jurisdiction and the year around it.
     */
    public function show(ChangeEvent $change): View
    {
        abort_unless($change->published_at?->lte(now()), 404);
        $change->load(['jurisdiction', 'policyInstrument']);
        $related = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])
            ->where('id', '!=', $change->id)
            ->where(fn ($q) => $q->where('jurisdiction_id', $change->jurisdiction_id)->when($change->policy_instrument_id, fn ($q) => $q->orWhere('policy_instrument_id', $change->policy_instrument_id)))
            ->orderByDesc('occurred_on')->limit(5)->get();
        $name = $change->jurisdiction?->name;
        $instrument = $change->policyInstrument;

        $seo = Seo::make(
            PageTitle::change($change),
            Str::limit(trim(preg_replace('/\s+/', ' ', $change->what_changed)), 155),
            $change->url(),
            // A site update (a new template version) stays listed in the change log
            // but is not offered to search engines as a change in the law.
            filled($change->what_changed) && filled($change->official_source_url) && ! $change->isSiteUpdate()
        )->withBreadcrumbs([['Home', route('home')], ['Changes', route('changes.index')], [(string) $change->occurred_on->year, route('changes.year', $change->occurred_on->year)], [$change->title, $change->url()]])
            ->withModified($change->updated_at)
            // Published when the entry first appeared here, not when the event
            // happened: a news reader asks the first question. The event date is
            // stated in the body and in the dateline below.
            ->withPublished($change->first_published_at ?? $change->occurred_on)
            ->withOgType($change->isSiteUpdate() ? 'website' : 'article')
            ->withCard('change', $change->slug, $change->updated_at)
            ->withFeed(route('changes.feed'))
            ->withAlternate('text/markdown', route('changes.context', $change->slug))
            // A change in the law is news; a new version of one of this site's own
            // templates is a page about the site, with no headline or dateline.
            ->withPageType($change->isSiteUpdate() ? 'WebPage' : 'NewsArticle', array_filter([
                'headline' => $change->isSiteUpdate() ? null : PageTitle::shorten($change->title, 110),
                'dateline' => $change->isSiteUpdate() ? null : trim(($name ?? '').', '.$change->occurred_on->format('j F Y'), ', '),
                'articleSection' => $change->isSiteUpdate() ? null : 'AI policy updates',
                'isBasedOn' => $change->official_source_url ?: null,
                'about' => array_values(array_filter([
                    $instrument ? ['@type' => $instrument->is_binding ? 'Legislation' : 'CreativeWork', 'name' => $instrument->short_title ?: $instrument->title, 'url' => $instrument->url()] : null,
                    $name ? ['@type' => 'Place', 'name' => $name] : null,
                ])),
            ]) + Seo::provenance($change));

        $answer = AnswerBox::change($change);
        $facts = array_values(array_filter([
            ['label' => 'Date', 'value' => $change->occurred_on->format('j F Y')],
            $change->jurisdiction ? ['label' => 'Jurisdiction', 'value' => $change->jurisdiction->name, 'href' => $change->jurisdiction->url()] : null,
            $instrument ? ['label' => 'Instrument', 'value' => $instrument->short_title ?: $instrument->title, 'href' => $instrument->url()] : null,
            ['label' => 'Impact', 'value' => $change->impactEnum()->label()],
            $change->statusAfterEnum() ? ['label' => 'Status afterwards', 'value' => $change->statusAfterEnum()->label()] : null,
            $change->official_source_url ? ['label' => 'Official source', 'value' => $change->source_publisher ?: parse_url($change->official_source_url, PHP_URL_HOST), 'href' => $change->official_source_url] : null,
        ]));

        return view('site.changes.show', compact('seo', 'change', 'related', 'instrument', 'answer', 'facts'));
    }

    public function year(int $year): View
    {
        $changes = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->whereBetween('occurred_on', ["{$year}-01-01", "{$year}-12-31"])->orderByDesc('occurred_on')->get();
        abort_if($changes->isEmpty(), 404);
        $years = ChangeEvent::publishedYears();

        $seo = Seo::make(
            "AI policy changes in {$year}",
            "Every source-backed AI policy change recorded for {$year}: new laws, application dates, guidance and consultations across jurisdictions, with official sources.",
            route('changes.year', $year)
        )->withBreadcrumbs([['Home', route('home')], ['Changes', route('changes.index')], [(string) $year, route('changes.year', $year)]])
            ->withFeed(route('changes.feed'));

        return view('site.changes.year', compact('seo', 'changes', 'year', 'years'));
    }

    public function feed(): Response
    {
        $changes = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->orderByDesc('occurred_on')->orderByDesc('id')->limit(50)->get();
        $xml = view('site.changes.feed', compact('changes'))->render();

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=900']);
    }
}
