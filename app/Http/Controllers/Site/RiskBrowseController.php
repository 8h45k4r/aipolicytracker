<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Services\ExternalData\ExternalDataset;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Researcher views over the imported AI Incident Database and MIT AI Risk
 * Repository rows: filters, charts, CSV/JSON exports. Attribution is rendered
 * on every page; licences travel with every export.
 */
class RiskBrowseController extends Controller
{
    public function __construct(private readonly ExternalDataset $data) {}

    public function risks(Request $request): View
    {
        [$query, $filters] = $this->riskQuery($request);
        $risks = $query->orderBy('domain')->orderBy('subdomain')->orderBy('ev_id')->paginate(50)->withQueryString();
        $mit = $this->data->mitRisk();
        $facets = [
            'domain' => ExternalRisk::selectRaw('domain, COUNT(*) as n')->whereNotNull('domain')->groupBy('domain')->orderBy('domain')->pluck('n', 'domain'),
            'entity' => ExternalRisk::selectRaw('entity, COUNT(*) as n')->whereNotNull('entity')->groupBy('entity')->pluck('n', 'entity'),
            'intent' => ExternalRisk::selectRaw('intent, COUNT(*) as n')->whereNotNull('intent')->groupBy('intent')->pluck('n', 'intent'),
            'timing' => ExternalRisk::selectRaw('timing, COUNT(*) as n')->whereNotNull('timing')->groupBy('timing')->pluck('n', 'timing'),
            'level' => ExternalRisk::selectRaw('level, COUNT(*) as n')->groupBy('level')->pluck('n', 'level'),
        ];
        $indexable = empty(array_filter($filters)) && $risks->currentPage() === 1;
        $seo = Seo::make('Browse AI risks: 2,500 risk entries from 74 frameworks', 'Search and filter the MIT AI Risk Repository database by domain, subdomain, entity, intent, timing and evidence level; export the result as CSV or JSON.', route('risk.risks'), $indexable)
            ->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], ['Browse risks', route('risk.risks')]])
            ->withJsonLd(['@type' => 'Dataset', 'name' => 'MIT AI Risk Repository: AI Risk Database (browseable copy)', 'description' => '2,500 AI risk entries from 74 frameworks coded by domain, subdomain, entity, intent and timing, with filters and CSV/JSON export.', 'url' => route('risk.risks'), 'license' => $mit['license_url'] ?? null, 'isBasedOn' => $mit['source_url'] ?? null, 'creator' => ['@type' => 'Organization', 'name' => 'MIT AI Risk Initiative', 'url' => 'https://airisk.mit.edu/'], 'citation' => $mit['citation'] ?? null, 'keywords' => ['AI risk', 'AI safety', 'responsible AI', 'risk taxonomy', 'AI governance'], 'isAccessibleForFree' => true, 'distribution' => [['@type' => 'DataDownload', 'encodingFormat' => 'text/csv', 'contentUrl' => route('risk.risks.export', 'csv')], ['@type' => 'DataDownload', 'encodingFormat' => 'application/json', 'contentUrl' => route('risk.risks.export', 'json')]]]);

        return view('site.risk.risks', compact('seo', 'risks', 'filters', 'facets', 'mit'));
    }

    public function risksExport(Request $request, string $format): StreamedResponse
    {
        [$query] = $this->riskQuery($request);
        $mit = $this->data->mitRisk();
        $name = 'mit-ai-risks-'.now()->format('Ymd').'.'.$format;
        $columns = ['ev_id', 'quick_ref', 'paper_title', 'level', 'risk_category', 'risk_subcategory', 'description', 'entity', 'intent', 'timing', 'domain', 'subdomain'];

        return $this->stream($query->orderBy('ev_id'), $columns, $format, $name, ['source' => $mit['source'] ?? 'MIT AI Risk Repository', 'license' => $mit['license'] ?? 'CC BY 4.0', 'license_url' => $mit['license_url'] ?? '', 'citation' => $mit['citation'] ?? '']);
    }

    public function frameworks(): View
    {
        $meta = $this->data->mitRisksMeta();
        $papers = collect($meta['papers'] ?? []);
        $mit = $this->data->mitRisk();
        $seo = Seo::make('AI risk frameworks: the 74 documents behind the MIT AI Risk Repository', 'Every framework, taxonomy and paper synthesised in the MIT AI Risk Repository, with the number of risk entries extracted from each.', route('risk.frameworks'))
            ->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], ['Frameworks', route('risk.frameworks')]]);

        return view('site.risk.frameworks', compact('seo', 'papers', 'mit', 'meta'));
    }

    public function incidentsBrowse(Request $request): View
    {
        [$query, $filters] = $this->incidentQuery($request);
        $incidents = $query->orderByDesc('occurred_on')->orderByDesc('incident_id')->paginate(50)->withQueryString();
        $facets = [
            'year' => ExternalIncident::selectRaw('year, COUNT(*) as n')->groupBy('year')->orderByDesc('year')->pluck('n', 'year'),
            'domain' => ExternalIncident::selectRaw('mit_domain, COUNT(*) as n')->whereNotNull('mit_domain')->groupBy('mit_domain')->orderByDesc('n')->pluck('n', 'mit_domain'),
            'harm' => ExternalIncident::selectRaw('harm_level, COUNT(*) as n')->whereNotNull('harm_level')->groupBy('harm_level')->orderByDesc('n')->pluck('n', 'harm_level'),
        ];
        $aiid = $this->data->aiid();
        $indexable = empty(array_filter($filters)) && $incidents->currentPage() === 1;
        $seo = Seo::make('Browse AI incidents: every record in the AI Incident Database', 'Filter AI incidents by year, risk domain, subdomain, country, sector, harm level and keyword; export as CSV or JSON. Metadata only, with a link to each incident record.', route('risk.incidents.browse'), $indexable)
            ->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], ['AI incidents', route('risk.incidents')], ['Browse', route('risk.incidents.browse')]])
            ->withJsonLd(['@type' => 'Dataset', 'name' => 'AI Incident Database: incident metadata (weekly copy)', 'description' => 'Every incident in the AI Incident Database with date, title, description, MIT risk domain and subdomain, causal coding, sector, country and harm level; filterable and exportable.', 'url' => route('risk.incidents.browse'), 'license' => $aiid['license_url'] ?? null, 'isBasedOn' => $aiid['source_url'] ?? null, 'creator' => ['@type' => 'Organization', 'name' => 'Responsible AI Collaborative', 'url' => 'https://incidentdatabase.ai/'], 'citation' => $aiid['citation'] ?? null, 'dateModified' => $aiid['snapshot_date'] ?? null, 'temporalCoverage' => '1983/..', 'keywords' => ['AI incidents', 'AI harms', 'AI safety', 'algorithmic harm', 'responsible AI'], 'isAccessibleForFree' => true, 'distribution' => [['@type' => 'DataDownload', 'encodingFormat' => 'text/csv', 'contentUrl' => route('risk.incidents.export', 'csv')], ['@type' => 'DataDownload', 'encodingFormat' => 'application/json', 'contentUrl' => route('risk.incidents.export', 'json')]]]);

        return view('site.risk.incidents-browse', compact('seo', 'incidents', 'filters', 'facets', 'aiid'));
    }

    /** Single-incident profile: every stored field, related incidents and the MIT risk entries that describe the same failure mode. */
    public function incidentShow(int $incident): View
    {
        $i = ExternalIncident::with('reports')->findOrFail($incident);
        $labels = ExternalIncident::domainLabels();
        $domainId = array_search($i->mit_domain, $labels, true) ?: null;
        $subdomainCode = $this->subdomainCode($i->mit_subdomain);

        $sameSubdomain = $i->mit_subdomain ? ExternalIncident::where('mit_subdomain', $i->mit_subdomain)->where('incident_id', '!=', $i->incident_id)->orderByDesc('occurred_on')->limit(6)->get() : collect();
        $deployer = $i->deployers[0] ?? null;
        $sameDeployer = $deployer ? ExternalIncident::where('incident_id', '!=', $i->incident_id)->where('deployers', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], json_encode($deployer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'%')->orderByDesc('occurred_on')->limit(6)->get() : collect();
        $risks = $subdomainCode ? ExternalRisk::where('subdomain', $subdomainCode)->whereIn('level', ['Risk Category', 'Risk Sub-Category'])->orderBy('quick_ref')->limit(8)->get() : collect();
        $related = ($ids = $i->similarIds()) ? ExternalIncident::whereIn('incident_id', $ids)->get()->sortBy(fn ($r) => array_search($r->incident_id, $ids, true))->values() : collect();
        $summary = $this->data->aiid();

        $seo = Seo::make(
            'AI incident #'.$i->incident_id.': '.$i->title,
            mb_substr(($i->description ?: $i->title).' Dated '.$i->occurred_on->format('j F Y').'; '.$i->report_count.' reports on the AI Incident Database.', 0, 155),
            route('risk.incidents.show', $i->incident_id),
            $i->isIndexable()
        )->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], ['Incidents', route('risk.incidents.browse')], ['#'.$i->incident_id, route('risk.incidents.show', $i->incident_id)]])
            ->withModified($i->modified_at ?? $i->snapshot_date)
            ->withPageType('Article', [
                'headline' => 'AI incident #'.$i->incident_id.': '.$i->title,
                'datePublished' => $i->occurred_on->toDateString(),
                // This record is the AI Incident Database's, under its own licence,
                // which is not the licence the rest of this site publishes under.
                'isBasedOn' => $i->citeUrl(),
                'license' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                'about' => array_values(array_filter([$i->mit_domain, $i->mit_subdomain])),
            ]);

        return view('site.risk.incident-show', compact('i', 'domainId', 'subdomainCode', 'sameSubdomain', 'sameDeployer', 'risks', 'related', 'summary', 'seo', 'deployer'));
    }

    /** Single MIT risk entry with its paper siblings, other frameworks describing the same subdomain, and matching incidents. */
    public function riskShow(string $ev): View
    {
        $r = ExternalRisk::findOrFail(str_replace('--', '#', $ev));
        $labels = ExternalIncident::domainLabels();
        $domain = $r->domain ? $this->data->mitDomain((string) $r->domain) : null;
        $subdomainMeta = null;
        foreach ($domain['subdomains'] ?? [] as $sd) {
            if (($sd['id'] ?? null) === $r->subdomain) {
                $subdomainMeta = $sd;
            }
        }
        $siblings = ExternalRisk::where('quick_ref', $r->quick_ref)->where('ev_id', '!=', $r->ev_id)->orderBy('ev_id')->limit(12)->get();
        $peers = $r->subdomain ? ExternalRisk::where('subdomain', $r->subdomain)->where('quick_ref', '!=', $r->quick_ref)->whereIn('level', ['Risk Category', 'Risk Sub-Category'])->orderBy('quick_ref')->limit(8)->get() : collect();
        $incidents = $subdomainMeta ? ExternalIncident::whereRaw('lower(mit_subdomain) = ?', [mb_strtolower(trim($subdomainMeta['name']))])->orderByDesc('occurred_on')->limit(6)->get() : collect();
        $meta = $this->data->mitRisksMeta();
        $paper = collect($meta['papers'] ?? [])->firstWhere('quick_ref', $r->quick_ref);

        $seo = Seo::make(
            ($r->risk_subcategory ?: $r->risk_category ?: 'Risk entry').' ('.$r->quick_ref.')',
            mb_substr(($r->description ?: 'Risk entry from '.$r->paper_title).' Coded as '.implode(', ', array_filter([$r->entity, $r->intent, $r->timing])).' in the MIT AI Risk Repository.', 0, 155),
            route('risk.risks.show', $ev),
            $r->isIndexable()
        )->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], ['Risk entries', route('risk.risks')], [$r->ev_id, route('risk.risks.show', $ev)]])
            // The sitemap has always published updated_at as this page's lastmod
            // while the page itself stated no dateModified at all. Two answers to
            // one question is worse than either, so the page now gives the same one.
            ->withModified($r->updated_at)
            ->withJsonLd([
                '@context' => 'https://schema.org', '@type' => 'DefinedTerm', 'name' => $r->risk_subcategory ?: $r->risk_category, 'description' => $r->description,
                'identifier' => $r->ev_id, 'inDefinedTermSet' => 'https://airisk.mit.edu/', 'license' => 'https://creativecommons.org/licenses/by/4.0/',
            ]);

        return view('site.risk.risk-show', compact('r', 'ev', 'domain', 'subdomainMeta', 'siblings', 'peers', 'incidents', 'paper', 'seo', 'labels'));
    }

    /** MIT subdomain code (e.g. "2.1") for an AIID subdomain label, via the taxonomy file. */
    private function subdomainCode(?string $label): ?string
    {
        if (! $label) {
            return null;
        }
        foreach ($this->data->mitRisk()['domains'] ?? [] as $d) {
            foreach ($d['subdomains'] ?? [] as $sd) {
                if (mb_strtolower(trim($sd['name'] ?? '')) === mb_strtolower(trim($label))) {
                    return $sd['id'] ?? null;
                }
            }
        }

        return null;
    }

    public function incidentsExport(Request $request, string $format): StreamedResponse
    {
        [$query] = $this->incidentQuery($request);
        $aiid = $this->data->aiid();
        $name = 'aiid-incidents-'.now()->format('Ymd').'.'.$format;
        $columns = ['incident_id', 'occurred_on', 'title', 'description', 'mit_domain', 'mit_subdomain', 'entity', 'intent', 'timing', 'harm_level', 'sectors', 'countries', 'deployers', 'developers', 'harmed', 'report_count'];

        return $this->stream($query->orderByDesc('occurred_on'), $columns, $format, $name, ['source' => $aiid['source'] ?? 'AI Incident Database', 'license' => $aiid['license'] ?? 'CC BY-SA 4.0', 'license_url' => $aiid['license_url'] ?? '', 'citation' => $aiid['citation'] ?? '', 'snapshot_date' => $aiid['snapshot_date'] ?? '']);
    }

    /** @return array{0: Builder, 1: array} */
    private function riskQuery(Request $request): array
    {
        $f = ['q' => trim((string) $this->param($request, 'q')), 'domain' => $this->param($request, 'domain'), 'subdomain' => $this->param($request, 'subdomain'), 'entity' => $this->param($request, 'entity'), 'intent' => $this->param($request, 'intent'), 'timing' => $this->param($request, 'timing'), 'level' => $this->param($request, 'level'), 'paper' => $this->param($request, 'paper')];
        $q = ExternalRisk::query();
        if ($f['domain'] !== null && $f['domain'] !== '' && ctype_digit((string) $f['domain'])) {
            $q->where('domain', (int) $f['domain']);
        }
        if ($f['subdomain'] && preg_match('/^\d+\.\d+$/', $f['subdomain'])) {
            $q->where('subdomain', $f['subdomain']);
        }
        foreach (['entity', 'intent', 'timing'] as $k) {
            if ($f[$k] && in_array($f[$k], ExternalRisk::CAUSAL[$k], true)) {
                $q->where($k, $f[$k]);
            }
        }
        if ($f['level'] && array_key_exists($f['level'], ExternalRisk::LEVELS)) {
            $q->where('level', $f['level']);
        }
        if ($f['paper'] && preg_match('/^[A-Za-z0-9_\-]{1,80}$/', $f['paper'])) {
            $q->where('quick_ref', $f['paper']);
        }
        if ($f['q'] !== '') {
            $like = '%'.mb_strtolower(mb_substr($f['q'], 0, 100)).'%';
            $q->where(fn ($w) => $w->whereRaw('LOWER(risk_category) LIKE ?', [$like])->orWhereRaw('LOWER(risk_subcategory) LIKE ?', [$like])->orWhereRaw('LOWER(description) LIKE ?', [$like])->orWhereRaw('LOWER(paper_title) LIKE ?', [$like]));
        }

        return [$q, $f];
    }

    /** @return array{0: Builder, 1: array} */
    private function incidentQuery(Request $request): array
    {
        $f = ['q' => trim((string) $this->param($request, 'q')), 'year' => $this->param($request, 'year'), 'domain' => $this->param($request, 'domain'), 'subdomain' => $this->param($request, 'subdomain'), 'country' => $this->param($request, 'country'), 'sector' => $this->param($request, 'sector'), 'harm' => $this->param($request, 'harm')];
        $q = ExternalIncident::query();
        if ($f['year'] && ctype_digit((string) $f['year'])) {
            $q->where('year', (int) $f['year']);
        }
        if ($f['domain']) {
            $q->where('mit_domain', mb_substr($f['domain'], 0, 80));
        }
        if ($f['subdomain']) {
            $q->where('mit_subdomain', mb_substr($f['subdomain'], 0, 120));
        }
        if ($f['country'] && preg_match('/^[A-Z]{2}$/', $f['country'])) {
            $q->where('countries', 'like', '%"'.$f['country'].'"%');
        }
        if ($f['sector']) {
            $q->where('sectors', 'like', '%'.str_replace(['%', '_', '"'], '', mb_strtolower(mb_substr($f['sector'], 0, 60))).'%');
        }
        if ($f['harm']) {
            $q->where('harm_level', mb_substr($f['harm'], 0, 60));
        }
        if ($f['q'] !== '') {
            $like = '%'.mb_strtolower(mb_substr($f['q'], 0, 100)).'%';
            $q->where(fn ($w) => $w->whereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw('LOWER(description) LIKE ?', [$like]));
        }

        return [$q, $f];
    }

    private function stream($query, array $columns, string $format, string $name, array $attribution): StreamedResponse
    {
        abort_unless(in_array($format, ['csv', 'json'], true), 404);
        $type = $format === 'csv' ? 'text/csv' : 'application/json';

        return response()->streamDownload(function () use ($query, $columns, $format, $attribution) {
            $out = fopen('php://output', 'w');
            if ($format === 'csv') {
                fputcsv($out, ['# '.$attribution['source'].' — '.$attribution['license'].' '.$attribution['license_url'].' — '.($attribution['citation'] ?? '')]);
                fputcsv($out, $columns);
            } else {
                fwrite($out, json_encode(['attribution' => $attribution, 'exported_at' => now()->toDateString()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                fwrite($out, "\n");
                fwrite($out, '{"rows":[');
            }
            $first = true;
            $query->chunk(500, function ($rows) use ($out, $columns, $format, &$first) {
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $c) {
                        $v = $row->{$c};
                        $values[$c] = is_array($v) ? ($format === 'csv' ? implode('|', $v) : $v) : ($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v);
                    }
                    if ($format === 'csv') {
                        fputcsv($out, Csv::row($values));
                    } else {
                        fwrite($out, ($first ? '' : ',').json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                        $first = false;
                    }
                }
            });
            if ($format === 'json') {
                fwrite($out, ']}');
            }
            fclose($out);
        }, $name, ['Content-Type' => $type.'; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** A filter value as a string, or null: an array such as `domain[]=x` is ignored rather than a 500. */
    private function param(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
