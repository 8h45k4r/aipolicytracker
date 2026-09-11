<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Models\PolicyInstrument;
use App\Services\ExternalData\ExternalDataset;
use App\Support\Seo;
use Illuminate\View\View;

class RiskController extends Controller
{
    public function __construct(private readonly ExternalDataset $data) {}

    public function index(): View
    {
        $mit = $this->data->mitRisk();
        $aiid = $this->data->aiid();
        $seo = Seo::make(
            'AI risk domains: taxonomy, incidents and the policies that respond',
            'The seven AI risk domains from the MIT AI Risk Repository, how often each appears in the AI Incident Database, and which AI policies in our tracker address them.',
            route('risk.index')
        )->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')]])
            ->withJsonLd(['@type' => 'Dataset', 'name' => 'AI risk domains (MIT AI Risk Repository) with incident counts', 'url' => route('risk.index'), 'license' => $mit['license_url'] ?? null, 'isBasedOn' => [$mit['source_url'] ?? null, $aiid['source_url'] ?? null], 'creator' => ['@type' => 'Organization', 'name' => 'MIT AI Risk Initiative']]);

        $riskByDomain = ExternalRisk::selectRaw('domain, COUNT(*) as n')->whereNotNull('domain')->groupBy('domain')->pluck('n', 'domain');
        $matrix = [];
        foreach (ExternalRisk::selectRaw('entity, intent, COUNT(*) as n')->whereNotNull('entity')->whereNotNull('intent')->groupBy('entity', 'intent')->get() as $row) {
            $matrix[$row->entity][$row->intent] = (int) $row->n;
        }
        $timing = ExternalRisk::selectRaw('timing, COUNT(*) as n')->whereNotNull('timing')->groupBy('timing')->pluck('n', 'timing');
        $incidentTotals = ['incidents' => ExternalIncident::count(), 'risks' => ExternalRisk::count()];

        return view('site.risk.index', ['seo' => $seo, 'mit' => $mit, 'aiid' => $aiid, 'riskByDomain' => $riskByDomain, 'matrix' => $matrix, 'timing' => $timing, 'incidentTotals' => $incidentTotals]);
    }

    public function domain(string $domain): View
    {
        $d = $this->data->mitDomain($domain);
        abort_unless($d, 404);
        $aiid = $this->data->aiid();
        $mit = $this->data->mitRisk();
        $incidents = $aiid['by_mit_domain'][$d['aiid_domain_label']] ?? null;
        $trend = collect($aiid['domain_by_year'] ?? [])->map(fn ($year) => $year[$d['aiid_domain_label']] ?? 0);
        $policies = $d['use_cases'] ? PolicyInstrument::published()->with('jurisdiction')->withTerm('use_case', $d['use_cases'])->orderBy('title')->limit(24)->get() : collect();

        $seo = Seo::make(
            "AI risk domain {$d['id']}: {$d['name']}",
            mb_substr(trim(($d['description'] ?: 'Subdomains, incident frequency and related AI policies for the MIT AI Risk Repository domain "'.$d['name'].'".')), 0, 155),
            route('risk.domain', $d['id'])
        )->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], [$d['name'], route('risk.domain', $d['id'])]])
            ->withJsonLd(['@type' => 'DefinedTermSet', 'name' => $d['name'], 'url' => route('risk.domain', $d['id']), 'isBasedOn' => $mit['source_url'] ?? null, 'license' => $mit['license_url'] ?? null]);

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
        $seo = Seo::make(
            'AI incidents: yearly trend, risk domains, sectors and countries',
            'Weekly-refreshed summary of the AI Incident Database: incidents per year, by MIT risk domain, sector of deployment and country, with links to each incident record.',
            route('risk.incidents')
        )->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], ['AI incidents', route('risk.incidents')]])
            ->withJsonLd(['@type' => 'Dataset', 'name' => 'AI Incident Database weekly summary', 'url' => route('risk.incidents'), 'license' => $aiid['license_url'] ?? null, 'isBasedOn' => $aiid['source_url'] ?? null, 'dateModified' => $aiid['snapshot_date'] ?? null, 'creator' => ['@type' => 'Organization', 'name' => 'Responsible AI Collaborative']]);

        return view('site.risk.incidents', ['seo' => $seo, 'aiid' => $aiid, 'mit' => $this->data->mitRisk()]);
    }
}
