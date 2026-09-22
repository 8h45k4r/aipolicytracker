<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Services\PolicyData\PolicyCatalog;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChangeController extends Controller
{
    public function index(Request $request, PolicyCatalog $catalog): View
    {
        $filters = $catalog->filtersFromRequest($request);
        $impact = in_array($request->query('impact'), ['urgent', 'high', 'routine'], true) ? $request->query('impact') : null;

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
        $changes = $query->orderByDesc('occurred_on')->orderByDesc('id')->paginate(25)->withQueryString();
        $urgent = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->whereIn('impact_level', ['urgent', 'high'])->orderByDesc('occurred_on')->limit(5)->get();
        $years = ChangeEvent::publishedYears();
        $indexable = empty($filters['q']) && $impact === null && empty($filters['jurisdiction']);

        $seo = Seo::make(
            'AI policy change log: dated, source-backed regulatory updates'.($changes->currentPage() > 1 ? ' (page '.$changes->currentPage().')' : ''),
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
            'seo' => $seo, 'changes' => $changes, 'urgent' => $urgent, 'years' => $years, 'filters' => $filters, 'impact' => $impact,
            'jurisdictions' => Jurisdiction::published()->orderBy('name')->get(['slug', 'name']),
        ]);
    }

    /**
     * A single change as a page. The record is short, so the page says what
     * changed, what it means, where it came from and where it sits, and then
     * points at the instrument, the jurisdiction and the year around it.
     */
    public function show(ChangeEvent $change): View
    {
        abort_unless($change->published_at, 404);
        $change->load(['jurisdiction', 'policyInstrument']);
        $related = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])
            ->where('id', '!=', $change->id)
            ->where(fn ($q) => $q->where('jurisdiction_id', $change->jurisdiction_id)->when($change->policy_instrument_id, fn ($q) => $q->orWhere('policy_instrument_id', $change->policy_instrument_id)))
            ->orderByDesc('occurred_on')->limit(5)->get();
        $name = $change->jurisdiction?->name;
        $instrument = $change->policyInstrument;

        $seo = Seo::make(
            Seo::fitTitle($change->title, [' ('.$name.', '.$change->occurred_on->format('M Y').')', ' ('.$change->occurred_on->format('M Y').')', '']),
            Str::limit(trim(preg_replace('/\s+/', ' ', $change->what_changed)), 155),
            $change->url(),
            filled($change->what_changed) && filled($change->official_source_url)
        )->withBreadcrumbs([['Home', route('home')], ['Changes', route('changes.index')], [(string) $change->occurred_on->year, route('changes.year', $change->occurred_on->year)], [$change->title, $change->url()]])
            ->withModified($change->updated_at)
            ->withPublished($change->occurred_on)
            ->withOgType('article')
            ->withFeed(route('changes.feed'))
            ->withAlternate('text/markdown', route('changes.context', $change->slug))
            ->withPageType('Article', array_filter([
                'headline' => $change->title,
                'articleSection' => 'AI policy change log',
                'isBasedOn' => $change->official_source_url ?: null,
                'about' => array_values(array_filter([
                    $instrument ? ['@type' => $instrument->is_binding ? 'Legislation' : 'CreativeWork', 'name' => $instrument->short_title ?: $instrument->title, 'url' => $instrument->url()] : null,
                    $name ? ['@type' => 'Place', 'name' => $name] : null,
                ])),
            ]) + Seo::provenance($change));

        return view('site.changes.show', compact('seo', 'change', 'related', 'instrument'));
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
