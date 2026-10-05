<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ExternalIncident;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\PolicyCatalog;
use App\Support\ContentCache;
use App\Support\Seo;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(PolicyCatalog $catalog): View
    {
        $seo = Seo::make(
            'AI Policy Tracker: Free Global AI Law and Policy Tracker',
            'Free AI policy tracker: AI laws, regulations and national AI policies around the world, on a map, with obligations, deadlines, templates and every official source.',
            route('home')
        );
        // Organization and WebSite are now emitted on every page by the layout, so
        // the homepage no longer adds its own copies. What it adds instead is the
        // corpus itself: the thing a reader or an answer engine arrives here for.
        $seo->withPageType('CollectionPage')
            ->withJsonLd(Seo::dataset(
                'AI policy and regulatory corpus',
                config('aipolicytracker.supporting'),
                url('/'),
                ['application/json' => route('open-data.download'), 'text/csv' => route('open-data.csv', 'policies')],
                $catalog->stats()['last_updated'] ? Carbon::parse($catalog->stats()['last_updated']) : null,
            ));

        // The page is the same for every anonymous reader and its queries are the
        // slowest part of answering it. Five minutes, the same horizon as the
        // catalogue's own figures; every job run flushes the cache sooner.
        $data = ContentCache::remember('home.data', 300, fn () => [
            'latestIncidents' => ExternalIncident::standard()->orderByDesc('occurred_on')->orderByDesc('incident_id')->limit(4)->get(),
            'incidentSnapshot' => ExternalIncident::max('synced_at') ?: ExternalIncident::max('snapshot_date'),
            'options' => $catalog->filterOptions(),
            'changes' => $catalog->latestChanges(6),
            'deadlines' => $catalog->upcomingDeadlines(5),
            'jurisdictions' => $catalog->featuredJurisdictions(),
            'featuredPolicies' => PolicyInstrument::published()->with('jurisdiction')->where('featured', true)->orderByDesc('is_binding')->orderBy('title')->limit(6)->get(),
            'stats' => $catalog->stats(),
            'audiences' => collect(config('content.audiences'))->map(fn ($p, $slug) => ['slug' => $slug, 'h1' => $p['h1'], 'taxonomy' => $p['taxonomy'], 'duties' => Obligation::published()->withTerm($p['taxonomy'], $p['term'])->count()])->values()->all(),
        ]);

        return view('site.home', $data + ['seo' => $seo]);
    }
}
