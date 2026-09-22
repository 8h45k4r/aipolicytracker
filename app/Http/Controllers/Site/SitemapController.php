<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use App\Models\Tool;
use App\Services\ExternalData\ExternalDataset;
use App\Services\PolicyData\FrameworkCrosswalk;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Sitemap index plus one sitemap per content type. Only indexable pages are
 * listed; lastmod comes from real record update timestamps.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $sections = [
            'static' => PolicyInstrument::published()->max('updated_at'),
            'jurisdictions' => Jurisdiction::published()->max('updated_at'),
            'policies' => PolicyInstrument::published()->max('updated_at'),
            'obligations' => Obligation::published()->max('updated_at'),
            'controls' => Control::published()->max('updated_at'),
            'changes' => ChangeEvent::published()->max('updated_at'),
            'resources' => PolicyInstrument::published()->max('updated_at'),
            // The research corpus: roughly 1,700 incident pages and 2,500 risk
            // entries that were reachable, indexable and in no sitemap at all,
            // which is most of what Search Console reports as discovered and not
            // indexed. A page nothing points a crawler at is a page nobody finds.
            'incidents' => ExternalIncident::query()->max('snapshot_date'),
            'risks' => ExternalRisk::query()->max('updated_at'),
        ];
        $entries = collect($sections)->map(fn ($mod, $key) => ['loc' => route('sitemap.section', $key), 'lastmod' => $mod ? Carbon::parse($mod)->toAtomString() : null]);

        return $this->xml(view('site.sitemap.index', compact('entries'))->render());
    }

    public function section(string $section): Response
    {
        $urls = match ($section) {
            'static' => $this->staticUrls(),
            'jurisdictions' => $this->jurisdictionUrls(),
            'policies' => $this->policyUrls(),
            'obligations' => $this->obligationUrls(),
            'controls' => $this->controlUrls(),
            'changes' => $this->changeUrls(),
            'resources' => $this->resourceUrls(),
            'incidents' => $this->incidentUrls(),
            'risks' => $this->riskUrls(),
        };

        return $this->xml(view('site.sitemap.urlset', compact('urls'))->render());
    }

    /**
     * These three streamed with get() while incidents and risks streamed with
     * lazy(). At the present size that is survivable, but the jurisdiction
     * filter also called isIndexable() per row, which issued one exists() query
     * each. withPublishedInstrument() collapses that to a single aggregate, and
     * lazy() keeps the whole set from being hydrated at once as it grows.
     */
    private function jurisdictionUrls()
    {
        return Jurisdiction::published()
            ->withPublishedInstrument()
            ->orderBy('slug')
            ->lazy(500)
            ->filter->isIndexable()
            ->map(fn ($j) => ['loc' => $j->url(), 'lastmod' => $j->updated_at?->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.9'])
            ->values();
    }

    private function policyUrls()
    {
        return PolicyInstrument::published()
            ->orderBy('slug')
            ->lazy(500)
            ->filter->isIndexable()
            ->map(fn ($p) => ['loc' => $p->url(), 'lastmod' => $p->updated_at?->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.9'])
            ->values();
    }

    private function controlUrls()
    {
        $urls = collect([['loc' => route('controls.index'), 'lastmod' => optional(Control::published()->max('updated_at'), fn ($m) => Carbon::parse($m)->toAtomString()), 'changefreq' => 'weekly', 'priority' => '0.8']]);

        return $urls->concat(Control::published()->with(['evidence', 'obligations'])->orderBy('slug')->get()
            ->filter->isIndexable()
            ->map(fn ($c) => ['loc' => $c->url(), 'lastmod' => $c->updated_at?->toAtomString(), 'changefreq' => 'monthly', 'priority' => '0.7']))->values();
    }

    private function obligationUrls()
    {
        return Obligation::published()
            ->with('policyInstrument')
            ->orderBy('slug')
            ->lazy(500)
            ->filter(fn ($o) => filled($o->summary) && $o->policyInstrument?->isIndexable())
            ->map(fn ($o) => ['loc' => $o->url(), 'lastmod' => $o->updated_at?->toAtomString(), 'changefreq' => 'monthly', 'priority' => '0.7'])
            ->values();
    }

    /**
     * One entry per recorded AI incident.
     *
     * Read lazily: this is the largest section by an order of magnitude, and a
     * sitemap that has to hold the whole table in memory to be served is a
     * sitemap that stops being served as the corpus grows.
     */
    private function incidentUrls()
    {
        return ExternalIncident::query()->orderBy('incident_id')->lazy(500)->filter->isIndexable()->map(fn ($i) => [
            'loc' => $i->url(),
            // What is actually known about when this record last moved. The
            // snapshot date is the fallback because it is when we last confirmed
            // the record, not when the incident happened.
            'lastmod' => ($i->modified_at ?? $i->snapshot_date)?->toAtomString(),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ])->values();
    }

    /**
     * One entry per MIT AI Risk Repository entry that earns a page of its own.
     *
     * Filtered on the same isIndexable() the page itself uses, because a sitemap
     * that lists a noindex page and a page that refuses the listing are two
     * halves of one contradiction, and an index resolves it by trusting neither.
     * This drops roughly a third of the 2,500 entries: the ones with no
     * description, which is all the page would have had.
     */
    private function riskUrls()
    {
        return ExternalRisk::query()->orderBy('ev_id')->lazy(500)->filter->isIndexable()->map(fn ($r) => [
            'loc' => $r->url(),
            'lastmod' => $r->updated_at?->toAtomString(),
            'changefreq' => 'monthly',
            'priority' => '0.5',
        ])->values();
    }

    private function staticUrls()
    {
        $mod = PolicyInstrument::published()->max('updated_at');
        $lastmod = $mod ? Carbon::parse($mod)->toAtomString() : null;
        $pages = [
            [route('home'), 'daily', '1.0'], [route('policies.index'), 'daily', '0.9'], [route('jurisdictions.index'), 'weekly', '0.9'],
            [route('obligations.index'), 'weekly', '0.8'], [route('compare.index'), 'monthly', '0.7'], [route('changes.index'), 'daily', '0.9'],
            [route('tools.applicability'), 'monthly', '0.7'], [route('risk.index'), 'weekly', '0.8'], [route('risk.incidents'), 'weekly', '0.8'], [route('risk.incidents.browse'), 'weekly', '0.7'], [route('risk.risks'), 'monthly', '0.7'], [route('risk.frameworks'), 'monthly', '0.6'], [route('risk.domain', 1), 'monthly', '0.6'], [route('risk.domain', 2), 'monthly', '0.6'], [route('risk.domain', 3), 'monthly', '0.6'], [route('risk.domain', 4), 'monthly', '0.6'], [route('risk.domain', 5), 'monthly', '0.6'], [route('risk.domain', 6), 'monthly', '0.6'], [route('risk.domain', 7), 'monthly', '0.6'], [route('open-data'), 'monthly', '0.7'], [route('methodology'), 'monthly', '0.6'], [route('verification'), 'weekly', '0.6'], [route('coverage'), 'weekly', '0.6'], [route('gaps'), 'daily', '0.5'], [route('corrections'), 'weekly', '0.5'], [route('reviewers'), 'weekly', '0.6'], [route('calendar'), 'weekly', '0.7'],
            [route('about'), 'monthly', '0.5'], [route('privacy'), 'yearly', '0.3'], [route('terms'), 'yearly', '0.3'], [route('contribute'), 'monthly', '0.5'], [route('subscribe.show'), 'monthly', '0.6'], [route('guides.index'), 'weekly', '0.7'],
        ];
        foreach (app(ExternalDataset::class)->mitRisk()['domains'] ?? [] as $d) {
            foreach ($d['subdomains'] ?? [] as $sd) {
                $pages[] = [route('risk.subdomain', [$d['id'], $sd['id']]), 'monthly', '0.6'];
            }
        }
        // Framework crosswalks. Only the pages that pass the same indexability threshold
        // the page itself applies are listed, so the sitemap never advertises a URL that
        // serves a noindex tag.
        $crosswalk = app(FrameworkCrosswalk::class);
        $pages[] = [route('frameworks.index'), 'weekly', '0.8'];
        foreach ($crosswalk->summary() as $framework) {
            if ($framework['obligations'] < FrameworkCrosswalk::MIN_INDEXABLE_OBLIGATIONS) {
                continue;
            }
            $pages[] = [route('frameworks.show', $framework['slug']), 'weekly', '0.8'];
            foreach ($crosswalk->jurisdictionsFor($framework['key']) as $row) {
                if ($row['rows'] >= FrameworkCrosswalk::MIN_INDEXABLE_ROWS) {
                    $pages[] = [route('frameworks.crosswalk', [$framework['slug'], $row['jurisdiction']->slug]), 'weekly', '0.7'];
                }
            }
        }
        foreach (Tool::published()->orderBy('sort_order')->pluck('slug') as $slug) {
            $pages[] = [route('tools.show', $slug), 'monthly', '0.8'];
        }
        // Guides are listed once, in the resources sitemap.
        //
        // No lastmod on an editorial or static page. The date used to be the
        // corpus-wide import time, which moved /privacy and /about on every deploy;
        // an index that catches a lastmod lying stops believing any of them, and
        // an absent date costs nothing. Pages built from records carry the date of
        // the records they are built from, below.
        $listings = [route('home'), route('policies.index'), route('jurisdictions.index'), route('obligations.index'), route('changes.index'), route('calendar'), route('coverage'), route('gaps'), route('corrections'), route('verification')];
        $urls = collect($pages)->map(fn ($p) => ['loc' => $p[0], 'lastmod' => in_array($p[0], $listings, true) ? $lastmod : null, 'changefreq' => $p[1], 'priority' => $p[2]]);
        // Indexable single-filter listings: one per jurisdiction and one per
        // obligation category, dated by their own records and gated on the same
        // rule the jurisdiction page applies to itself.
        foreach (Jurisdiction::published()->withPublishedInstrument()->orderBy('slug')->get() as $j) {
            if ($j->isIndexable()) {
                $mod = $j->policyInstruments()->published()->max('updated_at');
                $urls->push(['loc' => route('policies.index', ['jurisdiction' => $j->slug]), 'lastmod' => $mod ? Carbon::parse($mod)->toAtomString() : null, 'changefreq' => 'weekly', 'priority' => '0.6']);
            }
        }
        foreach (TaxonomyTerm::taxonomy('obligation_category')->get() as $c) {
            $mod = Obligation::published()->where('category', $c->slug)->max('updated_at');
            if ($mod) {
                $urls->push(['loc' => route('obligations.index', ['category' => $c->slug]), 'lastmod' => Carbon::parse($mod)->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.6']);
            }
        }

        return $urls->values();
    }

    private function changeUrls()
    {
        $years = ChangeEvent::publishedYearsLastModified();
        $urls = $years->map(fn ($m, $y) => ['loc' => route('changes.year', $y), 'lastmod' => $m ? Carbon::parse($m)->toAtomString() : null, 'changefreq' => 'weekly', 'priority' => '0.6'])->values();

        // Each change is a page now, dated by itself. Only one that can be
        // indexed is listed: the page applies the same test.
        return $urls->concat(
            ChangeEvent::published()->whereNotNull('official_source_url')->whereNotNull('what_changed')->orderByDesc('occurred_on')->lazy(500)
                ->map(fn ($c) => ['loc' => $c->url(), 'lastmod' => $c->updated_at?->toAtomString(), 'changefreq' => 'monthly', 'priority' => '0.5'])
        )->values();
    }

    private function resourceUrls()
    {
        $mod = PolicyInstrument::published()->max('updated_at');
        $lastmod = $mod ? Carbon::parse($mod)->toAtomString() : null;
        $urls = collect();
        foreach (array_keys(config('content.landings')) as $slug) {
            $urls->push(['loc' => route('landing', $slug), 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '0.8']);
        }
        $urls->push(['loc' => route('audiences.index'), 'lastmod' => null, 'changefreq' => 'weekly', 'priority' => '0.7']);
        foreach (config('content.audiences', []) as $slug => $page) {
            $duties = Obligation::published()->withTerm($page['taxonomy'], $page['term'])->count();
            if ($duties >= AudienceController::MIN_INDEXABLE_DUTIES) {
                $urls->push(['loc' => route('audiences.show', $slug), 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '0.8']);
            }
        }
        // Editorial pages carry no lastmod: nothing here knows when their text changed.
        foreach (array_keys(config('content.guides')) as $slug) {
            $urls->push(['loc' => route('guides.show', $slug), 'lastmod' => null, 'changefreq' => 'monthly', 'priority' => '0.7']);
        }
        foreach (array_keys(config('content.comparisons')) as $slug) {
            $urls->push(['loc' => route('compare.show', $slug), 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.7']);
        }

        return $urls;
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
