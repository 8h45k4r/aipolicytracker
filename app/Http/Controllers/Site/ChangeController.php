<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Services\PolicyData\PolicyCatalog;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $indexable = empty($filters['q']) && $impact === null && empty($filters['jurisdiction']) && $changes->currentPage() === 1;

        $seo = Seo::make(
            'AI policy change log: dated, source-backed regulatory updates',
            'Chronological log of AI policy changes across jurisdictions: what changed, practical impact, status after the change, official source and verification date. RSS available.',
            route('changes.index'),
            $indexable
        )->withBreadcrumbs([['Home', route('home')], ['Changes', route('changes.index')]])
            ->withFeed(route('changes.feed'))
            ->withPageType('CollectionPage', [
                'name' => 'AI policy change log',
                // A change has no page of its own; its stable address is the
                // machine-readable record, so that is what the list points at.
                'mainEntity' => Seo::itemList($changes->getCollection(), fn ($c) => $c->title, fn ($c) => $c->slug ? route('changes.context', $c->slug) : null, 'Recorded AI policy changes'),
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
