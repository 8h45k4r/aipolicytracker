<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
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
        return ExternalIncident::query()->orderBy('incident_id')->lazy(500)->map(fn ($i) => [
            'loc' => $i->url(),
            // What is actually known about when this record last moved. The
            // snapshot date is the fallback because it is when we last confirmed
            // the record, not when the incident happened.
            'lastmod' => ($i->modified_at ?? $i->snapshot_date)?->toAtomString(),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ])->values();
    }

    /** One entry per MIT AI Risk Repository entry that has a page of its own. */
    private function riskUrls()
    {
        return ExternalRisk::query()->orderBy('ev_id')->lazy(500)->map(fn ($r) => [
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
        if (config('billing.enabled')) {
            $pages[] = [route('pricing'), 'monthly', '0.6']; // noindex while billing is off, so listed only when it is on
        }
        foreach (app(\App\Services\ExternalData\ExternalDataset::class)->mitRisk()['domains'] ?? [] as $d) {
            foreach ($d['subdomains'] ?? [] as $sd) {
                $pages[] = [route('risk.subdomain', [$d['id'], $sd['id']]), 'monthly', '0.6'];
            }
        }
        foreach (\App\Models\Tool::published()->orderBy('sort_order')->pluck('slug') as $slug) {
            $pages[] = [route('tools.show', $slug), 'monthly', '0.8'];
        }
        foreach (array_keys(config('content.guides', [])) as $slug) {
            if (! in_array($slug, ['eu-ai-act', 'ai-regulation-india', 'ai-policy-nepal', 'ai-governance-singapore', 'ai-regulation-australia', 'ai-regulation-uk', 'ai-regulation-usa', 'ai-governance-uae', 'ai-regulation-south-asia'], true)) {
                $pages[] = [route('guides.show', $slug), 'monthly', '0.7'];
            }
        }
        $urls = collect($pages)->map(fn ($p) => ['loc' => $p[0], 'lastmod' => $lastmod, 'changefreq' => $p[1], 'priority' => $p[2]]);
        // Indexable single-filter listings: one per jurisdiction and one per obligation category.
        foreach (Jurisdiction::published()->orderBy('slug')->get() as $j) {
            if ($j->policyInstruments()->published()->exists()) {
                $urls->push(['loc' => route('policies.index', ['jurisdiction' => $j->slug]), 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '0.6']);
            }
        }
        foreach (TaxonomyTerm::taxonomy('obligation_category')->get() as $c) {
            if (Obligation::published()->where('category', $c->slug)->exists()) {
                $urls->push(['loc' => route('obligations.index', ['category' => $c->slug]), 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '0.6']);
            }
        }

        return $urls->values();
    }

    private function changeUrls()
    {
        $years = ChangeEvent::publishedYearsLastModified();

        return $years->map(fn ($m, $y) => ['loc' => route('changes.year', $y), 'lastmod' => $m ? Carbon::parse($m)->toAtomString() : null, 'changefreq' => 'weekly', 'priority' => '0.6'])->values();
    }

    private function resourceUrls()
    {
        $mod = PolicyInstrument::published()->max('updated_at');
        $lastmod = $mod ? Carbon::parse($mod)->toAtomString() : null;
        $urls = collect();
        foreach (array_keys(config('content.landings')) as $slug) {
            $urls->push(['loc' => route('landing', $slug), 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '0.8']);
        }
        foreach (array_keys(config('content.guides')) as $slug) {
            $urls->push(['loc' => route('guides.show', $slug), 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.7']);
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
