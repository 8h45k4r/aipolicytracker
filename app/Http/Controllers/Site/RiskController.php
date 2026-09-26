<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Services\ExternalData\ExternalDataset;
use App\Support\PageTitle;
use App\Support\RiskTaxonomy;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RiskController extends Controller
{
    public function __construct(private readonly ExternalDataset $data) {}

    public function index(): View
    {
        $treemapRisks = self::treemapRows($this->data->mitRisk(), 'risks');
        $treemapIncidents = self::treemapRows($this->data->mitRisk(), 'incidents');
        $mit = $this->data->mitRisk();
        $aiid = $this->data->aiid();
        $seo = Seo::make(
            'AI risk domains: taxonomy, incidents and the policies that respond',
            'The seven AI risk domains from the MIT AI Risk Repository, how often each appears in the AI Incident Database, and which AI policies in our tracker address them.',
            route('risk.index')
        )->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')]])
            // A Dataset without a description is a dataset a machine cannot summarise,
            // and Search Console reports it as a missing required field.
            ->withJsonLd(array_filter([
                '@type' => 'Dataset',
                'name' => 'AI risk domains (MIT AI Risk Repository) with incident counts',
                'description' => 'The seven risk domains and twenty-four subdomains of the MIT AI Risk Repository, each mapped to the recorded incidents classified under it, with counts per domain. Incident counts are refreshed weekly from the AI Incident Database; the counts reflect reporting and classification rather than true frequency.',
                'url' => route('risk.index'),
                'license' => $mit['license_url'] ?? null,
                'isBasedOn' => array_values(array_filter([$mit['source_url'] ?? null, $aiid['source_url'] ?? null])),
                'creator' => ['@type' => 'Organization', 'name' => 'MIT AI Risk Initiative'],
                'dateModified' => $aiid['snapshot_date'] ?? null,
                'isAccessibleForFree' => true,
                'keywords' => ['AI risk taxonomy', 'AI incidents', 'AI policy'],
            ], fn ($v) => $v !== null && $v !== '' && $v !== []))
            ->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => [
                ['@type' => 'Question', 'name' => 'What are the seven domains of AI risk?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Discrimination and toxicity; privacy and security; misinformation; malicious actors and misuse; human-computer interaction; socioeconomic and environmental harms; and AI system safety, failures and limitations, as defined by the MIT AI Risk Repository domain taxonomy.']],
                ['@type' => 'Question', 'name' => 'Which AI risk domain has the most recorded incidents?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'In the current AI Incident Database snapshot, malicious actors and misuse leads, followed by AI system safety, failures and limitations, and discrimination and toxicity. Counts reflect reporting and classification, not true frequency.']],
            ]]);

        $riskByDomain = ExternalRisk::selectRaw('domain, COUNT(*) as n')->whereNotNull('domain')->groupBy('domain')->pluck('n', 'domain');
        $matrix = [];
        foreach (ExternalRisk::selectRaw('entity, intent, COUNT(*) as n')->whereNotNull('entity')->whereNotNull('intent')->groupBy('entity', 'intent')->get() as $row) {
            $matrix[$row->entity][$row->intent] = (int) $row->n;
        }
        $timing = ExternalRisk::selectRaw('timing, COUNT(*) as n')->whereNotNull('timing')->groupBy('timing')->pluck('n', 'timing');
        $incidentTotals = ['incidents' => ExternalIncident::count(), 'risks' => ExternalRisk::count()];
        $narrative = self::narrative($aiid, $mit);

        return view('site.risk.index', ['seo' => $seo, 'mit' => $mit, 'aiid' => $aiid, 'riskByDomain' => $riskByDomain, 'matrix' => $matrix, 'timing' => $timing, 'incidentTotals' => $incidentTotals, 'treemapRisks' => $treemapRisks, 'treemapIncidents' => $treemapIncidents, 'narrative' => $narrative]);
    }

    /** Subdomain profile: definition, causal breakdowns, frameworks, risk entries and incidents for one MIT subdomain (e.g. 2.1). */
    public function subdomain(string $domain, string $sub): View|RedirectResponse
    {
        $domainId = RiskTaxonomy::domainId($domain);
        $code = $domainId ? RiskTaxonomy::subdomainCode($domainId, $sub) : null;
        abort_unless($domainId && $code, 404);
        // The numbered address ("/ai-risk/1/1.2") was the original; it now
        // redirects to the named one, which is the only one published.
        if ($domain !== RiskTaxonomy::domainSlug($domainId) || $sub !== RiskTaxonomy::subdomainSlug($code)) {
            return redirect()->to(RiskTaxonomy::subdomainUrl($domainId, $code), 301);
        }
        $d = $this->data->mitDomain($domainId);
        abort_unless($d, 404);
        $sub = $code;
        $meta = collect($d['subdomains'] ?? [])->firstWhere('id', $sub);
        abort_unless($meta, 404);
        $mit = $this->data->mitRisk();
        $risksQuery = ExternalRisk::where('subdomain', $sub);
        $riskCount = (clone $risksQuery)->count();
        $breakdown = [];
        foreach (['entity', 'intent', 'timing'] as $k) {
            $breakdown[$k] = (clone $risksQuery)->selectRaw("{$k}, COUNT(*) as n")->whereNotNull($k)->groupBy($k)->orderByDesc('n')->pluck('n', $k);
        }
        $byLevel = (clone $risksQuery)->selectRaw('level, COUNT(*) as n')->groupBy('level')->pluck('n', 'level');
        $papers = (clone $risksQuery)->selectRaw('quick_ref, MAX(paper_title) as title, COUNT(*) as n')->groupBy('quick_ref')->orderByDesc('n')->limit(12)->get();
        $risks = (clone $risksQuery)->whereIn('level', ['Risk Category', 'Risk Sub-Category'])->orderBy('quick_ref')->orderBy('ev_id')->paginate(25)->withQueryString();
        $incidentsQuery = ExternalIncident::whereRaw('lower(mit_subdomain) = ?', [mb_strtolower(trim($meta['name']))]);
        $incidentCount = (clone $incidentsQuery)->count();
        $incidentYears = (clone $incidentsQuery)->selectRaw('year, COUNT(*) as n')->groupBy('year')->orderBy('year')->pluck('n', 'year');
        $incidents = (clone $incidentsQuery)->orderByDesc('occurred_on')->limit(10)->get();
        $policies = $d['use_cases'] ? PolicyInstrument::published()->with('jurisdiction')->withTerm('use_case', $d['use_cases'])->orderBy('title')->limit(12)->get() : collect();

        $seo = Seo::make(
            PageTitle::fit($meta['name'], [': AI Risk, Incidents & Laws', ': AI Risk']),
            mb_substr(($meta['description'] ?: $meta['name']).' '.number_format($riskCount).' risk entries and '.number_format($incidentCount).' recorded incidents.', 0, 155),
            RiskTaxonomy::subdomainUrl($d['id'], $sub)
        )->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], [$d['name'], RiskTaxonomy::domainUrl($d['id'])], [$sub, RiskTaxonomy::subdomainUrl($d['id'], $sub)]])
            ->withJsonLd(['@type' => 'DefinedTerm', 'name' => $meta['name'], 'description' => $meta['description'] ?? null, 'identifier' => $sub, 'inDefinedTermSet' => 'https://airisk.mit.edu/', 'license' => $mit['license_url'] ?? null]);

        return view('site.risk.subdomain', compact('seo', 'mit', 'd', 'meta', 'sub', 'riskCount', 'breakdown', 'byLevel', 'papers', 'risks', 'incidentCount', 'incidentYears', 'incidents', 'policies'));
    }

    /**
     * Numbers behind the AI-risk narrative: growth, who is harmed and who deploys, where harm is
     * recorded versus where rules exist, and how instruments map to domains. Cached for an hour.
     */
    /**
     * How recorded AI harms actually arise: who caused it, whether it was meant,
     * and whether it happened before or after the system was released.
     *
     * These three fields are the MIT causal taxonomy and are coded on 1,496 of
     * 1,663 records (90%). They are read from the incident table rather than the
     * weekly snapshot because the API sync keeps the table ahead of it between
     * imports, and they are charted nowhere else on the site.
     *
     * `classified` is returned so the page can state the denominator. Every
     * percentage here is of the classified records, not of the corpus, and
     * saying so is the difference between a finding and an overclaim: the 167
     * uncoded records are unknown, not "none of the above".
     *
     * @return array{classified:int, total:int, entity:array, intent:array, timing:array, matrix:array}
     */
    public static function causalProfile(): array
    {
        $coded = fn (string $column) => ExternalIncident::query()
            ->whereNotNull($column)->where($column, '!=', '')
            ->selectRaw($column.' as k, count(*) as c')->groupBy($column)
            ->orderByDesc('c')->pluck('c', 'k')->map(fn ($c) => (int) $c)->all();

        $matrix = [];
        $rows = ExternalIncident::query()
            ->whereNotNull('entity')->where('entity', '!=', '')
            ->whereNotNull('intent')->where('intent', '!=', '')
            ->selectRaw('entity, intent, count(*) as c')->groupBy('entity', 'intent')->get();
        foreach ($rows as $row) {
            $matrix[$row->entity][$row->intent] = (int) $row->c;
        }

        return [
            'classified' => ExternalIncident::whereNotNull('entity')->where('entity', '!=', '')->count(),
            'total' => ExternalIncident::count(),
            'entity' => $coded('entity'),
            'intent' => $coded('intent'),
            'timing' => $coded('timing'),
            'matrix' => $matrix,
        ];
    }

    public static function narrative(array $aiid, array $mit): array
    {
        return Cache::remember('risk-narrative-v1', 3600, function () use ($aiid, $mit) {
            $now = now();
            $last12 = ExternalIncident::where('occurred_on', '>=', $now->copy()->subMonths(12)->toDateString())->count();
            $prev12 = ExternalIncident::whereBetween('occurred_on', [$now->copy()->subMonths(24)->toDateString(), $now->copy()->subMonths(12)->toDateString()])->count();
            $byYear = collect($aiid['incidents_per_year'] ?? []);
            $domainShare = [];
            foreach (['2019', (string) ($byYear->keys()->max() - 1)] as $y) {
                $row = $aiid['domain_by_year'][$y] ?? [];
                $tot = max(1, array_sum($row));
                $domainShare[$y] = collect($row)->map(fn ($n) => round(100 * $n / $tot))->all();
            }
            $deployers = [];
            $harmed = [];
            foreach (ExternalIncident::select(['deployers', 'harmed'])->cursor() as $i) {
                foreach ($i->deployers ?? [] as $d) {
                    $deployers[$d] = ($deployers[$d] ?? 0) + 1;
                }
                foreach ($i->harmed ?? [] as $h) {
                    $harmed[$h] = ($harmed[$h] ?? 0) + 1;
                }
            }
            arsort($deployers);
            arsort($harmed);
            $countries = collect($aiid['by_country'] ?? [])->take(15);
            $jurisdictions = Jurisdiction::published()->whereIn('iso_code', $countries->keys()->all())->withCount(['policyInstruments as instruments' => fn ($q) => $q->published(), 'policyInstruments as binding' => fn ($q) => $q->published()->where('is_binding', true)])->get()->keyBy('iso_code');
            $gap = $countries->map(fn ($n, $cc) => ['code' => $cc, 'incidents' => $n, 'jurisdiction' => $jurisdictions[$cc] ?? null])->values()->all();
            $domainInstruments = [];
            foreach ($mit['domains'] ?? [] as $d) {
                $domainInstruments[$d['id']] = $d['use_cases'] ? PolicyInstrument::published()->withTerm('use_case', $d['use_cases'])->count() : 0;
            }

            return [
                'last12' => $last12, 'prev12' => $prev12, 'growth' => $prev12 ? round(100 * ($last12 - $prev12) / $prev12) : null,
                'domain_share' => $domainShare, 'top_deployers' => array_slice($deployers, 0, 10, true), 'top_harmed' => array_slice($harmed, 0, 10, true),
                'gap' => $gap, 'domain_instruments' => $domainInstruments, 'milestones' => config('content.risk_milestones', []),
                'with_country' => $aiid['totals']['with_country'] ?? 0, 'classified' => $aiid['totals']['classified_mit'] ?? 0,
            ];
        });
    }

    /** Rows for the domain → subdomain treemap: cells sized by risk entries (or incidents). */
    public static function treemapRows(array $mit, string $measure = 'risks'): array
    {
        $riskBySub = ExternalRisk::selectRaw('subdomain, COUNT(*) as n')->whereNotNull('subdomain')->groupBy('subdomain')->pluck('n', 'subdomain');
        $incBySub = ExternalIncident::selectRaw('lower(mit_subdomain) as s, COUNT(*) as n')->whereNotNull('mit_subdomain')->groupBy('s')->pluck('n', 's');
        $rows = [];
        foreach ($mit['domains'] ?? [] as $d) {
            $cells = [];
            foreach ($d['subdomains'] ?? [] as $sd) {
                $value = $measure === 'incidents' ? (int) ($incBySub[mb_strtolower(trim($sd['name']))] ?? 0) : (int) ($riskBySub[$sd['id']] ?? 0);
                $cells[] = ['label' => $sd['id'].' '.$sd['name'], 'short' => $sd['id'], 'url' => RiskTaxonomy::subdomainUrl($d['id'], $sd['id']), 'value' => $value];
            }
            $rows[] = ['label' => $d['id'].'. '.$d['name'], 'url' => RiskTaxonomy::domainUrl($d['id']), 'total' => array_sum(array_column($cells, 'value')), 'cells' => $cells];
        }

        return $rows;
    }

    public function domain(string $domain): View|RedirectResponse
    {
        $domainId = RiskTaxonomy::domainId($domain);
        abort_unless($domainId, 404);
        if ($domain !== RiskTaxonomy::domainSlug($domainId)) {
            return redirect()->to(RiskTaxonomy::domainUrl($domainId), 301);
        }
        $d = $this->data->mitDomain($domainId);
        abort_unless($d, 404);
        $aiid = $this->data->aiid();
        $mit = $this->data->mitRisk();
        $incidents = $aiid['by_mit_domain'][$d['aiid_domain_label']] ?? null;
        $trend = collect($aiid['domain_by_year'] ?? [])->map(fn ($year) => $year[$d['aiid_domain_label']] ?? 0);
        $policies = $d['use_cases'] ? PolicyInstrument::published()->with('jurisdiction')->withTerm('use_case', $d['use_cases'])->orderBy('title')->limit(24)->get() : collect();

        $seo = Seo::make(
            PageTitle::fit(str_replace(' & ', ' and ', $d['name']).' AI Risks', [': Incidents, Taxonomy & Laws', ': Incidents & Laws']),
            mb_substr(trim(($d['description'] ?: 'Subdomains, incident frequency and related AI policies for the MIT AI Risk Repository domain "'.$d['name'].'".')), 0, 155),
            RiskTaxonomy::domainUrl($d['id'])
        )->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], [$d['name'], RiskTaxonomy::domainUrl($d['id'])]])
            ->withJsonLd(['@type' => 'DefinedTermSet', 'name' => $d['name'], 'url' => RiskTaxonomy::domainUrl($d['id']), 'isBasedOn' => $mit['source_url'] ?? null, 'license' => $mit['license_url'] ?? null]);

        $subRisks = ExternalRisk::selectRaw('subdomain, COUNT(*) as n')->where('domain', (int) $d['id'])->whereNotNull('subdomain')->groupBy('subdomain')->pluck('n', 'subdomain');
        $subIncidents = ExternalIncident::selectRaw('mit_subdomain, COUNT(*) as n')->where('mit_domain', $d['aiid_domain_label'])->whereNotNull('mit_subdomain')->groupBy('mit_subdomain')->pluck('n', 'mit_subdomain');
        $riskCount = ExternalRisk::where('domain', (int) $d['id'])->count();
        $topPapers = ExternalRisk::selectRaw('quick_ref, MAX(paper_title) as title, COUNT(*) as n')->where('domain', (int) $d['id'])->groupBy('quick_ref')->orderByDesc('n')->limit(8)->get();
        $recent = ExternalIncident::where('mit_domain', $d['aiid_domain_label'])->orderByDesc('occurred_on')->limit(8)->get();

        return view('site.risk.domain', ['seo' => $seo, 'mit' => $mit, 'domain' => $d, 'incidents' => $incidents, 'trend' => $trend, 'policies' => $policies, 'aiid' => $aiid, 'subRisks' => $subRisks, 'subIncidents' => $subIncidents, 'riskCount' => $riskCount, 'topPapers' => $topPapers, 'recent' => $recent]);
    }

    public function incidents(): View
    {
        $aiid = $this->data->aiid();
        // Latest records come from the read-model table, which the live API sync keeps ahead of the weekly snapshot.
        $latest = ExternalIncident::with('reports')->orderByDesc('occurred_on')->orderByDesc('incident_id')->limit(30)->get();
        $live = ['count' => ExternalIncident::count(), 'synced_at' => ExternalIncident::max('synced_at'), 'latest_id' => ExternalIncident::max('incident_id'), 'recent' => ExternalIncident::where('occurred_on', '>=', now()->subDays(30)->toDateString())->count()];
        $modified = $live['synced_at'] ?: ($aiid['snapshot_date'] ?? null);
        $modified = $modified ? Carbon::parse($modified) : null;
        $seo = Seo::make(
            'AI incidents: latest records, yearly trend, risk domains, sectors and countries',
            'The latest AI incidents synced from the AI Incident Database, with incidents per year, by MIT risk domain, sector of deployment and country, and a profile page for every record.',
            route('risk.incidents')
        )->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], ['AI incidents', route('risk.incidents')]])
            ->withModified($modified)
            ->withJsonLd(array_filter([
                '@type' => 'Dataset',
                'name' => 'AI Incident Database: latest incidents and weekly summary',
                'description' => 'The most recently recorded AI incidents from the AI Incident Database, with a weekly summary and each incident classified by MIT risk domain and subdomain, sector and country. Refreshed weekly from the published export; every page shows the snapshot date it was built from.',
                'url' => route('risk.incidents'),
                'license' => $aiid['license_url'] ?? null,
                'isBasedOn' => $aiid['source_url'] ?? null,
                'dateModified' => $modified?->toDateString(),
                'creator' => ['@type' => 'Organization', 'name' => 'Responsible AI Collaborative'],
                'isAccessibleForFree' => true,
            ], fn ($v) => $v !== null && $v !== '' && $v !== []))
            ->withJsonLd(['@type' => 'ItemList', 'name' => 'Latest recorded AI incidents', 'itemListOrder' => 'https://schema.org/ItemListOrderDescending', 'numberOfItems' => $latest->count(), 'itemListElement' => $latest->take(10)->values()->map(fn ($i, $k) => ['@type' => 'ListItem', 'position' => $k + 1, 'url' => $i->url(), 'name' => $i->title])->all()]);

        return view('site.risk.incidents', ['seo' => $seo, 'aiid' => $aiid, 'mit' => $this->data->mitRisk(), 'latest' => $latest, 'live' => $live, 'causal' => self::causalProfile()]);
    }
}
