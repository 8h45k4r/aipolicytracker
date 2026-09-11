<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\PolicyCatalog;
use App\Models\ExternalIncident;
use App\Support\Seo;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(PolicyCatalog $catalog): View
    {
        $seo = Seo::make(
            'AIPolicyTracker: Track AI policy. Build compliant AI.',
            'Open, source-backed AI policy tracker: laws, regulations, standards, obligations, deadlines and compliance actions across the EU, UK, US, India, Nepal, Singapore, Australia, UAE and more.',
            route('home')
        )->withJsonLd(Seo::organization())->withJsonLd(Seo::website());

        return view('site.home', ['latestIncidents' => ExternalIncident::orderByDesc('occurred_on')->orderByDesc('incident_id')->limit(4)->get(), 'incidentSnapshot' => ExternalIncident::max('synced_at') ?: ExternalIncident::max('snapshot_date'), 
            'seo' => $seo,
            'options' => $catalog->filterOptions(),
            'changes' => $catalog->latestChanges(6),
            'deadlines' => $catalog->upcomingDeadlines(5),
            'jurisdictions' => $catalog->featuredJurisdictions(),
            'featuredPolicies' => PolicyInstrument::published()->with('jurisdiction')->where('featured', true)->orderByDesc('is_binding')->orderBy('title')->limit(6)->get(),
            'stats' => $catalog->stats(),
        ]);
    }
}
