<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\DigestIssue;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use App\Models\TemplateVersion;
use App\Models\Tool;
use App\Models\TransitionMeasure;
use App\Services\ExternalData\ExternalDataset;
use App\Services\Glossary\GlossaryTerms;
use App\Services\Hubs\HubCatalog;
use App\Services\Implementation\Trackers;
use App\Services\PolicyData\FrameworkCrosswalk;
use App\Services\Report\StateOfAiRegulation;
use App\Services\Reviewers\ReviewerRoster;
use App\Services\Templates\TemplateCatalog;
use App\Support\ContentDates;
use App\Support\RiskTaxonomy;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Sitemap index plus one sitemap per content type. Only indexable pages are
 * listed. A record's lastmod is the date its content last changed (ContentDates):
 * its last verification, its last recorded revision, or its first publication,
 * never updated_at, which moves whenever an import rewrites the row.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $policies = $this->corpusModified();
        $changes = ContentDates::latest(ChangeEvent::class, 'change');
        $sections = [
            'static' => $policies,
            'jurisdictions' => collect([ContentDates::latest(Jurisdiction::class), $policies])->filter()->max(),
            'policies' => $policies,
            'obligations' => ContentDates::latest(Obligation::class, 'obligation'),
            'controls' => ContentDates::latest(Control::class, 'control'),
            'changes' => $changes,
            'updates' => $changes,
            'templates' => TemplateVersion::max('generated_at'),
            'resources' => $policies,
            // The research corpus: roughly 1,700 incident pages and 2,500 risk
            // entries that were reachable, indexable and in no sitemap at all,
            // which is most of what Search Console reports as discovered and not
            // indexed. A page nothing points a crawler at is a page nobody finds.
            'incidents' => ExternalIncident::query()->max('snapshot_date'),
            'risks' => ExternalRisk::query()->max('updated_at'),
        ];
        $entries = collect($sections)->map(fn ($mod, $key) => ['loc' => route('sitemap.section', $key), 'lastmod' => $mod ? Carbon::parse($mod)->toAtomString() : null]);
        // The news sitemap is listed only while it has something in it: an empty
        // one is a promise of news to a crawler that will find none.
        if (($news = $this->newsItems())->isNotEmpty()) {
            $entries->put('news', ['loc' => route('sitemap.news'), 'lastmod' => $news->max('first_published_at')?->toAtomString()]);
        }

        return $this->xml(view('site.sitemap.index', compact('entries'))->render());
    }

    /**
     * The incident and risk sections walk thousands of rows from weekly snapshots, and
     * crawlers fetch every section on each visit, so those two are rebuilt at most every
     * quarter hour. The record sections stay live: a publish or takedown shows at once.
     */
    public function section(string $section): Response
    {
        return $this->xml(in_array($section, ['incidents', 'risks'], true)
            ? Cache::remember('sitemap:section:'.$section, now()->addMinutes(15), fn () => $this->renderSection($section))
            : $this->renderSection($section));
    }

    private function renderSection(string $section): string
    {
        $urls = match ($section) {
            'static' => $this->staticUrls(),
            'jurisdictions' => $this->jurisdictionUrls(),
            'policies' => $this->policyUrls(),
            'obligations' => $this->obligationUrls(),
            'controls' => $this->controlUrls(),
            'changes' => $this->changeUrls(),
            'updates' => $this->updatesUrls(),
            'templates' => $this->templateUrls(),
            'resources' => $this->resourceUrls(),
            'incidents' => $this->incidentUrls(),
            'risks' => $this->riskUrls(),
        };

        return view('site.sitemap.urlset', compact('urls'))->render();
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
        // A country page lists its instruments, so it changes when one of them does.
        $policies = $this->policyDates();
        $byJurisdiction = PolicyInstrument::published()->pluck('jurisdiction_id', 'id')
            ->map(fn ($j, $id) => ['j' => $j, 'date' => $policies[$id] ?? null])
            ->groupBy('j')->map(fn ($rows) => $rows->pluck('date')->filter()->max());

        return Jurisdiction::published()
            ->withPublishedInstrument()
            ->orderBy('slug')
            ->lazy(500)
            ->filter->isPageIndexable()
            ->map(fn ($j) => ['loc' => $j->url(), 'lastmod' => $this->atom(collect([ContentDates::of($j), $byJurisdiction[$j->id] ?? null])->filter()->max()), 'changefreq' => 'weekly', 'priority' => '0.9'])
            ->values();
    }

    private function policyUrls()
    {
        $dates = $this->policyDates();

        return PolicyInstrument::published()
            ->orderBy('slug')
            ->lazy(500)
            ->filter->isIndexable()
            ->map(fn ($p) => ['loc' => $p->url(), 'lastmod' => $this->atom($dates[$p->id] ?? null), 'changefreq' => 'weekly', 'priority' => '0.9'])
            ->values();
    }

    private function controlUrls()
    {
        $revised = ContentDates::revisions('control');
        $urls = collect([['loc' => route('controls.index'), 'lastmod' => $this->atom(ContentDates::latest(Control::class, 'control')), 'changefreq' => 'weekly', 'priority' => '0.8']]);

        return $urls->concat(Control::published()->with(['evidence', 'obligations'])->orderBy('slug')->get()
            ->filter->isIndexable()
            ->map(fn ($c) => ['loc' => $c->url(), 'lastmod' => $this->atom(ContentDates::of($c, $revised[$c->slug] ?? null)), 'changefreq' => 'monthly', 'priority' => '0.7']))->values();
    }

    private function obligationUrls()
    {
        $revised = ContentDates::revisions('obligation');

        return Obligation::published()
            ->with('policyInstrument')
            ->orderBy('slug')
            ->lazy(500)
            ->filter(fn ($o) => filled($o->summary) && $o->policyInstrument?->isIndexable())
            ->map(fn ($o) => ['loc' => $o->url(), 'lastmod' => $this->atom(ContentDates::of($o, $revised[$o->slug] ?? null)), 'changefreq' => 'monthly', 'priority' => '0.7'])
            ->values();
    }

    /**
     * Each published instrument's content date, id => date. A policy page shows its
     * duties and its dated changes, so a revision to one of those moves the page too.
     *
     * @return array<int, Carbon|null>
     */
    private function policyDates(): array
    {
        $dates = [];
        $revised = ContentDates::revisions('policy');
        foreach (PolicyInstrument::published()->get(['id', 'slug', 'last_verified_at', 'published_at']) as $p) {
            $dates[$p->id] = ContentDates::of($p, $revised[$p->slug] ?? null);
        }
        $children = [
            'obligation' => Obligation::published()->get(['slug', 'policy_instrument_id', 'last_verified_at', 'published_at']),
            'change' => ChangeEvent::published()->whereNotNull('policy_instrument_id')->get(['slug', 'policy_instrument_id', 'last_verified_at', 'published_at']),
        ];
        foreach ($children as $type => $records) {
            $revised = ContentDates::revisions($type);
            foreach ($records as $child) {
                $id = $child->policy_instrument_id;
                if (array_key_exists($id, $dates) && ($date = ContentDates::of($child, $revised[$child->slug] ?? null))) {
                    $dates[$id] = $dates[$id] ? max($dates[$id], $date) : $date;
                }
            }
        }

        return $dates;
    }

    /** The newest content date across the published instruments, for pages built from them all. */
    private function corpusModified(): ?Carbon
    {
        return collect($this->policyDates())->filter()->max();
    }

    private function atom(?\DateTimeInterface $date): ?string
    {
        return $date ? Carbon::instance($date)->toAtomString() : null;
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
     * This drops more than half of the 2,500 entries: the ones with no
     * description, or one too short to be a page of its own.
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
        $lastmod = $this->atom($this->corpusModified());
        $pages = [
            [route('home'), 'daily', '1.0'], [route('policies.index'), 'daily', '0.9'], [route('jurisdictions.index'), 'weekly', '0.9'],
            [route('obligations.index'), 'weekly', '0.8'], [route('compare.index'), 'monthly', '0.7'], [route('changes.index'), 'daily', '0.9'],
            [route('tools.applicability'), 'monthly', '0.7'], [route('risk.index'), 'weekly', '0.8'], [route('risk.incidents'), 'weekly', '0.8'], [route('risk.incidents.browse'), 'weekly', '0.7'], [route('risk.risks'), 'monthly', '0.7'], [route('risk.frameworks'), 'monthly', '0.6'], [RiskTaxonomy::domainUrl(1), 'monthly', '0.6'], [RiskTaxonomy::domainUrl(2), 'monthly', '0.6'], [RiskTaxonomy::domainUrl(3), 'monthly', '0.6'], [RiskTaxonomy::domainUrl(4), 'monthly', '0.6'], [RiskTaxonomy::domainUrl(5), 'monthly', '0.6'], [RiskTaxonomy::domainUrl(6), 'monthly', '0.6'], [RiskTaxonomy::domainUrl(7), 'monthly', '0.6'], [route('open-data'), 'monthly', '0.7'], [route('developers'), 'monthly', '0.7'], [route('mcp'), 'monthly', '0.6'], [route('methodology'), 'monthly', '0.6'], [route('glossary'), 'monthly', '0.7'], [route('ai-policy-examples'), 'weekly', '0.7'], [route('assessments.index'), 'monthly', '0.6'], [route('verification'), 'weekly', '0.6'], [route('freshness'), 'weekly', '0.5'], [route('independence'), 'monthly', '0.5'], [route('coverage'), 'weekly', '0.6'], [route('gaps'), 'daily', '0.5'], [route('corrections'), 'weekly', '0.5'], [route('reviewers'), 'weekly', '0.6'], [route('calendar'), 'weekly', '0.7'],
            [route('about'), 'monthly', '0.5'], [route('team'), 'monthly', '0.5'], [route('funding'), 'monthly', '0.5'], [route('privacy'), 'yearly', '0.3'], [route('terms'), 'yearly', '0.3'], [route('contribute'), 'monthly', '0.5'], [route('subscribe.show'), 'monthly', '0.6'], [route('guides.index'), 'weekly', '0.7'],
        ];
        // Indexed whether or not checkout is on: it carries the licence terms and the enquiry form.
        $pages[] = [route('pricing'), 'monthly', '0.6'];
        foreach (app(ExternalDataset::class)->mitRisk()['domains'] ?? [] as $d) {
            foreach ($d['subdomains'] ?? [] as $sd) {
                $pages[] = [RiskTaxonomy::subdomainUrl($d['id'], $sd['id']), 'monthly', '0.6'];
            }
        }
        // Framework crosswalks. Only the pages that pass the same indexability threshold
        // the page itself applies are listed, so the sitemap never advertises a URL that
        // serves a noindex tag.
        $crosswalk = app(FrameworkCrosswalk::class);
        $pages[] = [route('frameworks.index'), 'weekly', '0.8'];
        $pages[] = [route('frameworks.compare'), 'weekly', '0.7'];
        $pages[] = [route('transition.index'), 'weekly', '0.8'];
        $pages[] = [route('state-of.show'), 'weekly', '0.8'];
        foreach (StateOfAiRegulation::frozenQuarters() as $q) {
            $pages[] = [route('state-of.quarter', $q), 'yearly', '0.5'];
        }
        // A term answered on a record page (the EU AI Act) is listed there, not twice.
        foreach (GlossaryTerms::all() as $term) {
            if (! $term['canonical']) {
                $pages[] = [route('glossary.show', $term['id']), 'monthly', '0.6'];
            }
        }
        $pages[] = [route('embed.index'), 'monthly', '0.4'];
        foreach (app(ReviewerRoster::class)->published() as $r) {
            $pages[] = [route('reviewers.show', $r['slug']), 'monthly', '0.4'];
        }
        $pages[] = [route('transition.methodology'), 'monthly', '0.5'];
        // Measure pages, on the same rule the page applies to its own robots tag.
        foreach (TransitionMeasure::whereNotNull('published_at')->orderBy('slug')->get()->filter->isIndexable() as $measure) {
            $pages[] = [$measure->url(), 'monthly', '0.5'];
        }
        foreach (TransitionController::LANDINGS as $landing => $meta) {
            if (TransitionMeasure::whereNotNull('published_at')->where('measure_type', $meta['type'])->where('review_status', 'verified')->count() >= TransitionController::MIN_INDEXABLE_LANDING) {
                $pages[] = [route('transition.landing', $landing), 'weekly', '0.7'];
            }
        }
        // The enforcement, implementation and standards trackers: listed only once they pass
        // the threshold their own robots tag applies.
        if (Trackers::enforcementIndexable()) {
            $pages[] = [route('enforcement.index'), 'weekly', '0.8'];
        }
        if (Trackers::standardsIndexable()) {
            $pages[] = [route('standards.index'), 'weekly', '0.7'];
        }
        foreach (Trackers::instrumentsWithMeasures() as $instrument) {
            if (Trackers::implementationIndexable($instrument)) {
                $pages[] = [route('policies.implementation', $instrument->slug), 'weekly', '0.7'];
            }
        }
        $pages[] = [route('deadlines.engine'), 'monthly', '0.7'];
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
        foreach (Tool::published()->whereNotIn('slug', array_keys(TemplateCatalog::redirects()))->orderBy('sort_order')->pluck('slug') as $slug) {
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
        // Indexable single-filter listings: one per obligation category, dated by
        // its own records. A listing for one jurisdiction is not listed: the
        // country page answers that question and lists every instrument it would.
        $revised = ContentDates::revisions('obligation');
        $byCategory = Obligation::published()->get(['slug', 'category', 'last_verified_at', 'published_at'])
            ->groupBy('category')->map(fn ($group) => $group->map(fn ($o) => ContentDates::of($o, $revised[$o->slug] ?? null))->filter()->max());
        foreach (TaxonomyTerm::taxonomy('obligation_category')->get() as $c) {
            if ($mod = $byCategory[$c->slug] ?? null) {
                $urls->push(['loc' => route('obligations.index', ['category' => $c->slug]), 'lastmod' => $this->atom($mod), 'changefreq' => 'weekly', 'priority' => '0.6']);
            }
        }

        return $urls->values();
    }

    private function changeUrls()
    {
        $changes = $this->changeDates();
        $urls = $changes->filter(fn ($c) => $c['year'] !== '')->groupBy('year')->sortKeysDesc()->map(fn ($group, $y) => ['loc' => route('changes.year', $y), 'lastmod' => $this->atom($group->max('date')), 'changefreq' => 'weekly', 'priority' => '0.6'])->values();
        $revised = ContentDates::revisions('change');

        // Each change is a page now, dated by itself. Only one that can be
        // indexed is listed: the page applies the same test, and a site update
        // (a new version of a template here) is not a change in the law.
        return $urls->concat(
            ChangeEvent::published()->regulatory()->whereNotNull('official_source_url')->whereNotNull('what_changed')->orderByDesc('occurred_on')->lazy(500)
                ->map(fn ($c) => ['loc' => $c->url(), 'lastmod' => $this->atom(ContentDates::of($c, $revised[$c->slug] ?? null)), 'changefreq' => 'monthly', 'priority' => '0.5'])
        )->values();
    }

    /**
     * Every published change with its content date and where it falls, for the
     * archive pages built from them.
     *
     * @return Collection<int, array{year: string, month: string, day: string, jurisdiction: int, date: Carbon|null}>
     */
    private function changeDates()
    {
        $revised = ContentDates::revisions('change');

        return ChangeEvent::published()->get(['slug', 'occurred_on', 'jurisdiction_id', 'last_verified_at', 'published_at'])
            ->map(fn ($c) => [
                'year' => substr((string) $c->occurred_on, 0, 4),
                'month' => substr((string) $c->occurred_on, 0, 7),
                'day' => substr((string) $c->occurred_on, 0, 10),
                'jurisdiction' => (int) $c->jurisdiction_id,
                'date' => ContentDates::of($c, $revised[$c->slug] ?? null),
            ]);
    }

    /**
     * Google News sitemap: change entries first published in the last 48 hours.
     * The event itself must also be recent. On a database built from scratch,
     * every row is "first published" at build time, and without the second test
     * a 2017 entry would be offered as today's news.
     */
    public function news(): Response
    {
        return $this->xml(view('site.sitemap.news', ['changes' => $this->newsItems()])->render());
    }

    private function newsItems()
    {
        return ChangeEvent::published()->regulatory()->with('jurisdiction')
            ->whereNotNull('official_source_url')->whereNotNull('what_changed')
            ->where('first_published_at', '>=', now()->subHours(48))
            ->where('occurred_on', '>=', now()->subDays(14)->toDateString())
            ->orderByDesc('first_published_at')->limit(1000)->get();
    }

    /**
     * The updates hub and its archives, each listed only when it is indexable:
     * the pages apply the same threshold to themselves.
     */
    private function templateUrls()
    {
        $latest = TemplateVersion::latestAll();
        $newest = collect($latest)->max('generated_at');
        $urls = collect([['loc' => route('templates.index'), 'lastmod' => $newest?->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.8']]);
        foreach (TemplateCatalog::all()->keys() as $slug) {
            $urls->push(['loc' => route('templates.show', $slug), 'lastmod' => isset($latest[$slug]) ? $latest[$slug]->generated_at->toAtomString() : null, 'changefreq' => 'weekly', 'priority' => '0.7']);
        }
        // Landing pages per framework and type, dated by the newest template they list.
        foreach (TemplateCatalog::facets() as $f) {
            $mod = TemplateCatalog::filter(TemplateCatalog::all(), [$f['facet'] => $f['key']])->keys()->map(fn ($slug) => $latest[$slug]->generated_at ?? null)->filter()->max();
            $urls->push(['loc' => $f['url'], 'lastmod' => $mod?->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.7']);
        }

        return $urls;
    }

    private function updatesUrls()
    {
        $changes = $this->changeDates();
        $urls = collect([['loc' => route('updates.index'), 'lastmod' => $this->atom($changes->max('date')), 'changefreq' => 'daily', 'priority' => '0.9']]);

        foreach ($changes->groupBy('month')->sortKeysDesc() as $month => $group) {
            if ($group->count() >= UpdatesController::MIN_INDEXABLE) {
                $urls->push(['loc' => route('updates.month', $month), 'lastmod' => $this->atom($group->max('date')), 'changefreq' => 'weekly', 'priority' => '0.6']);
            }
        }
        foreach ($changes->groupBy('day') as $day => $group) {
            if ($group->count() >= UpdatesController::MIN_INDEXABLE_DAY) {
                $urls->push(['loc' => route('updates.day', $day), 'lastmod' => $this->atom($group->max('date')), 'changefreq' => 'monthly', 'priority' => '0.5']);
            }
        }
        $byJurisdiction = $changes->groupBy('jurisdiction');
        foreach (Jurisdiction::published()->withPublishedInstrument()->whereIn('id', $byJurisdiction->keys())->orderBy('slug')->get() as $j) {
            $group = $byJurisdiction[$j->id];
            if ($j->isIndexable() && $group->count() >= UpdatesController::MIN_INDEXABLE) {
                $urls->push(['loc' => route('updates.jurisdiction', $j->slug), 'lastmod' => $this->atom($group->max('date')), 'changefreq' => 'weekly', 'priority' => '0.6']);
            }
        }
        foreach (DigestIssue::orderByDesc('sent_on')->get() as $issue) {
            if ($issue->isIndexable()) {
                $urls->push(['loc' => $issue->url(), 'lastmod' => $issue->updated_at?->toAtomString(), 'changefreq' => 'yearly', 'priority' => '0.4']);
            }
        }
        if (DigestIssue::query()->exists()) {
            $urls->push(['loc' => route('newsletter.index'), 'lastmod' => DigestIssue::max('updated_at') ? Carbon::parse(DigestIssue::max('updated_at'))->toAtomString() : null, 'changefreq' => 'weekly', 'priority' => '0.5']);
        }

        return $urls->values();
    }

    private function resourceUrls()
    {
        $lastmod = $this->atom($this->corpusModified());
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
        // Regional hubs and the listed comparison pairs, each under its own thin guard.
        $byRegion = Jurisdiction::published()->whereIn('jurisdiction_type', ['country', 'supranational'])->withPublishedInstrument()->get()->groupBy('region');
        foreach (HubCatalog::regions() as $regionSlug => $regionName) {
            if (($byRegion[$regionName] ?? collect())->filter->isPageIndexable()->count() >= (int) config('hubs.min_region_countries', 3)) {
                $urls->push(['loc' => HubCatalog::regionUrl($regionSlug), 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '0.8']);
            }
        }
        $indexableSlugs = $byRegion->flatten()->filter->isPageIndexable()->pluck('slug')->all();
        foreach (HubCatalog::indexablePairs() as $pair) {
            [$a, $b] = HubCatalog::parsePair($pair);
            if (in_array($a, $indexableSlugs, true) && in_array($b, $indexableSlugs, true) && ! HubCatalog::curatedFor($a, $b)) {
                $urls->push(['loc' => route('compare.show', $pair), 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.6']);
            }
        }

        return $urls;
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
