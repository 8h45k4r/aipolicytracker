<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Services\ExternalData\ExternalDataset;
use App\Support\Seo;
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
            ->withJsonLd(['@type' => 'Dataset', 'name' => 'MIT AI Risk Repository (browseable copy)', 'url' => route('risk.risks'), 'license' => $mit['license_url'] ?? null, 'isBasedOn' => $mit['source_url'] ?? null]);

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
            ->withBreadcrumbs([['Home', route('home')], ['AI risk', route('risk.index')], ['AI incidents', route('risk.incidents')], ['Browse', route('risk.incidents.browse')]]);

        return view('site.risk.incidents-browse', compact('seo', 'incidents', 'filters', 'facets', 'aiid'));
    }

    public function incidentsExport(Request $request, string $format): StreamedResponse
    {
        [$query] = $this->incidentQuery($request);
        $aiid = $this->data->aiid();
        $name = 'aiid-incidents-'.now()->format('Ymd').'.'.$format;
        $columns = ['incident_id', 'occurred_on', 'title', 'description', 'mit_domain', 'mit_subdomain', 'entity', 'intent', 'timing', 'harm_level', 'sectors', 'countries', 'deployers', 'developers', 'harmed', 'report_count'];

        return $this->stream($query->orderByDesc('occurred_on'), $columns, $format, $name, ['source' => $aiid['source'] ?? 'AI Incident Database', 'license' => $aiid['license'] ?? 'CC BY-SA 4.0', 'license_url' => $aiid['license_url'] ?? '', 'citation' => $aiid['citation'] ?? '', 'snapshot_date' => $aiid['snapshot_date'] ?? '']);
    }

    /** @return array{0: \Illuminate\Database\Eloquent\Builder, 1: array} */
    private function riskQuery(Request $request): array
    {
        $f = ['q' => trim((string) $request->query('q')), 'domain' => $request->query('domain'), 'subdomain' => $request->query('subdomain'), 'entity' => $request->query('entity'), 'intent' => $request->query('intent'), 'timing' => $request->query('timing'), 'level' => $request->query('level'), 'paper' => $request->query('paper')];
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

    /** @return array{0: \Illuminate\Database\Eloquent\Builder, 1: array} */
    private function incidentQuery(Request $request): array
    {
        $f = ['q' => trim((string) $request->query('q')), 'year' => $request->query('year'), 'domain' => $request->query('domain'), 'subdomain' => $request->query('subdomain'), 'country' => $request->query('country'), 'sector' => $request->query('sector'), 'harm' => $request->query('harm')];
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
                        fputcsv($out, array_values($values));
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
}
