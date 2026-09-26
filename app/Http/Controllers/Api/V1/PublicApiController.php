<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Deadline;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Models\FrameworkMapping;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use App\Services\ExternalData\ExternalDataset;
use App\Services\ExternalData\IncidentEnrichment;
use App\Services\ExternalData\IncidentSensitivity;
use App\Services\PolicyData\FrameworkCrosswalk;
use App\Services\PolicyData\PolicyCatalog;
use App\Services\PolicyData\PolicySerializer;
use App\Support\PageTitle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only public API (v1). Responses are cached and carry public cache headers.
 * Only published records are exposed; review and admin data never leave the server.
 */
class PublicApiController extends Controller
{
    public function __construct(
        private readonly PolicySerializer $serializer,
        private readonly PolicyCatalog $catalog,
        private readonly FrameworkCrosswalk $crosswalk,
        private readonly ExternalDataset $external,
    ) {}

    public function root(): JsonResponse
    {
        return $this->respond([
            'name' => config('aipolicytracker.site_name').' public API',
            'version' => 'v1',
            'license' => config('aipolicytracker.data_license'),
            'openapi' => route('openapi'),
            'docs' => route('open-data'),
            'endpoints' => [
                'jurisdictions' => route('api.v1.jurisdictions'),
                'policies' => route('api.v1.policies'),
                'obligations' => route('api.v1.obligations'),
                'controls' => route('api.v1.controls'),
                'changes' => route('api.v1.changes'),
                'taxonomies' => route('api.v1.taxonomies'),
                'frameworks' => route('api.v1.frameworks'),
                'deadlines' => route('api.v1.deadlines'),
                'incidents' => route('api.v1.incidents'),
                'risks' => route('api.v1.risks'),
            ],
            'disclaimer' => config('aipolicytracker.disclaimer'),
        ]);
    }

    public function jurisdictions(): JsonResponse
    {
        return $this->cached('api.jurisdictions', fn () => [
            'data' => Jurisdiction::published()->orderBy('name')->get()->map(fn ($j) => $this->serializer->jurisdiction($j))->all(),
        ]);
    }

    public function jurisdiction(string $slug): JsonResponse
    {
        $j = Jurisdiction::published()->where('slug', $slug)->with(['policyInstruments' => fn ($q) => $q->published()->with('jurisdiction')])->first();
        abort_unless($j, 404);

        return $this->cached('api.jurisdiction.'.$slug, fn () => ['data' => $this->serializer->jurisdiction($j, true)]);
    }

    public function policies(Request $request): JsonResponse
    {
        $filters = $this->catalog->filtersFromRequest($request);
        $perPage = min(max((int) $request->query('per_page', 25), 1), config('aipolicytracker.max_per_page'));
        $key = 'api.policies.'.hash('xxh128', json_encode($filters).$perPage.max(1, $request->integer('page', 1)));

        return $this->cached($key, function () use ($filters, $perPage) {
            $page = $this->catalog->policyQuery($filters)->paginate($perPage)->withQueryString();

            return [
                'data' => $page->getCollection()->map(fn ($p) => $this->serializer->policySummary($p))->all(),
                'meta' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'filters' => $filters, 'license' => config('aipolicytracker.data_license'), 'license_url' => config('aipolicytracker.data_license_url')],
                'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
            ];
        });
    }

    public function policy(string $slug): JsonResponse
    {
        $p = PolicyInstrument::published()->where('slug', $slug)->first();
        abort_unless($p, 404);

        return $this->cached('api.policy.'.$slug, fn () => ['data' => $this->serializer->policy($p)]);
    }

    public function obligations(Request $request): JsonResponse
    {
        $filters = $this->catalog->filtersFromRequest($request);
        $perPage = min(max((int) $request->query('per_page', 25), 1), config('aipolicytracker.max_per_page'));
        $key = 'api.obligations.'.hash('xxh128', json_encode($filters).$perPage.max(1, $request->integer('page', 1)));

        return $this->cached($key, function () use ($filters, $perPage) {
            $page = $this->catalog->obligationQuery($filters)->paginate($perPage)->withQueryString();

            return [
                'data' => $page->getCollection()->map(fn ($o) => $this->serializer->obligation($o))->all(),
                'meta' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'filters' => $filters, 'license' => config('aipolicytracker.data_license'), 'license_url' => config('aipolicytracker.data_license_url')],
                'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
            ];
        });
    }

    public function obligation(string $slug): JsonResponse
    {
        $o = Obligation::published()->where('slug', $slug)->with(['policyInstrument.jurisdiction', 'terms', 'frameworkMappings', 'evidenceArtifacts', 'applicabilityRules', 'section'])->first();
        abort_unless($o, 404);

        return $this->cached('api.obligation.'.$slug, fn () => ['data' => $this->serializer->obligation($o)]);
    }

    public function controls(Request $request): JsonResponse
    {
        $kind = array_key_exists((string) $request->query('kind'), Control::KINDS) ? $request->query('kind') : null;
        $framework = preg_match('/^[a-z0-9_]+$/', (string) $request->query('framework')) ? $request->query('framework') : null;

        return $this->cached('api.controls.'.hash('xxh128', $kind.$framework), fn () => [
            'data' => Control::published()
                ->when($kind, fn ($q) => $q->where('kind', $kind))
                ->when($framework, fn ($q) => $q->whereHas('frameworkReferences', fn ($r) => $r->where('framework', $framework)))
                ->orderBy('title')->get()->map(fn ($c) => $this->serializer->control($c))->all(),
            'meta' => ['kinds' => Control::KINDS, 'filters' => array_filter(compact('kind', 'framework')), 'license' => config('aipolicytracker.data_license'), 'license_url' => config('aipolicytracker.data_license_url')],
        ]);
    }

    public function control(string $slug): JsonResponse
    {
        $c = Control::published()->where('slug', $slug)->first();
        abort_unless($c, 404);

        return $this->cached('api.control.'.$slug, fn () => ['data' => $this->serializer->control($c)]);
    }

    public function changes(Request $request): JsonResponse
    {
        $since = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('since')) ? $request->query('since') : null;
        $jurisdiction = preg_match('/^[a-z0-9-]+$/', (string) $request->query('jurisdiction')) ? $request->query('jurisdiction') : null;
        $key = 'api.changes.'.hash('xxh128', $since.$jurisdiction.max(1, $request->integer('page', 1)));

        return $this->cached($key, function () use ($since, $jurisdiction) {
            $page = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])
                ->when($since, fn ($q) => $q->where('occurred_on', '>=', $since))
                ->when($jurisdiction, fn ($q) => $q->whereHas('jurisdiction', fn ($j) => $j->where('slug', $jurisdiction)))
                ->orderByDesc('occurred_on')->paginate(50)->withQueryString();

            return [
                'data' => $page->getCollection()->map(fn ($c) => $this->serializer->change($c))->all(),
                'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'license' => config('aipolicytracker.data_license'), 'license_url' => config('aipolicytracker.data_license_url')],
                'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl(), 'rss' => route('changes.feed')],
            ];
        });
    }

    public function taxonomies(): JsonResponse
    {
        return $this->cached('api.taxonomies', fn () => [
            'data' => TaxonomyTerm::orderBy('taxonomy')->orderBy('sort_order')->get()->groupBy('taxonomy')->map(fn ($terms) => $terms->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name, 'description' => $t->description])->values())->all(),
        ]);
    }

    /** Every framework that legal duties are crosswalked to, with its coverage. */
    public function frameworks(): JsonResponse
    {
        return $this->cached('api.frameworks', fn () => [
            'data' => $this->crosswalk->summary()->map(fn (array $f) => [
                'key' => $f['key'],
                'slug' => $f['slug'],
                'name' => $f['name'],
                'publisher' => $f['publisher'],
                'published' => $f['published'],
                'certifiable' => $f['certifiable'],
                'url' => $f['url'],
                'obligations_mapped' => $f['obligations'],
                'mappings' => $f['mappings'],
                'jurisdictions' => $f['jurisdictions'],
                'binding_obligations_mapped' => $f['binding'],
                'href' => $f['obligations'] > 0 ? route('api.v1.framework', $f['slug']) : null,
            ])->all(),
            'meta' => ['note' => 'Mappings are editorial judgements recorded against one obligation. They cite clause numbers only and reproduce no standard text.'],
        ]);
    }

    /** One framework: every mapped duty, grouped by the clause or function it cites. */
    public function framework(string $framework): JsonResponse
    {
        $key = $this->crosswalk->keyForSlug($framework);
        abort_unless($key, 404);
        $data = $this->crosswalk->framework($key);
        abort_if($data['mappings'] === 0, 404);

        return $this->cached('api.framework.'.$framework, fn () => [
            'data' => [
                'key' => $key,
                'slug' => $data['meta']['slug'],
                'name' => $data['meta']['name'],
                'unit' => $data['meta']['unit'],
                'obligations_mapped' => $data['obligations'],
                'mappings' => $data['mappings'],
                'families' => $data['families']->map(fn ($rows, $family) => [
                    'family' => $family,
                    'mappings' => $rows->map(fn ($m) => $this->mapping($m))->values()->all(),
                ])->values()->all(),
                'jurisdictions' => $data['jurisdictions']->map(fn (array $row) => [
                    'slug' => $row['jurisdiction']->slug,
                    'name' => $row['jurisdiction']->name,
                    'mappings' => $row['rows'],
                    'href' => route('api.v1.framework.crosswalk', [$data['meta']['slug'], $row['jurisdiction']->slug]),
                ])->all(),
            ],
        ]);
    }

    /** One jurisdiction's duties mapped to one framework, with the coverage denominator. */
    public function frameworkCrosswalk(string $framework, string $jurisdiction): JsonResponse
    {
        $key = $this->crosswalk->keyForSlug($framework);
        abort_unless($key, 404);
        $j = Jurisdiction::published()->where('slug', $jurisdiction)->first();
        abort_unless($j, 404);
        $data = $this->crosswalk->crosswalk($key, $j);
        abort_if($data['rows']->isEmpty(), 404);

        return $this->cached('api.framework.'.$framework.'.'.$jurisdiction, fn () => [
            'data' => [
                'framework' => ['key' => $key, 'slug' => $data['meta']['slug'], 'name' => $data['meta']['name']],
                'jurisdiction' => ['slug' => $j->slug, 'name' => $j->name],
                'obligations_mapped' => $data['rows']->count(),
                'obligations_recorded' => $data['total_obligations'],
                'mappings' => $data['rows']->map(fn ($m) => $this->mapping($m))->all(),
            ],
            'meta' => ['note' => 'obligations_recorded is how many duties this platform has broken out for the jurisdiction, not how many the law contains.'],
        ]);
    }

    /** Compliance dates recorded against instruments and duties. */
    public function deadlines(Request $request): JsonResponse
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('from')) ? $request->query('from') : null;
        $key = 'api.deadlines.'.hash('xxh128', (string) $from.max(1, $request->integer('page', 1)));

        return $this->cached($key, function () use ($from) {
            $page = Deadline::with(['policyInstrument.jurisdiction', 'obligation'])
                ->whereHas('policyInstrument', fn ($q) => $q->published())
                ->when($from, fn ($q) => $q->where('due_on', '>=', $from))
                ->orderByRaw('due_on is null, due_on')->paginate(100)->withQueryString();

            return [
                'data' => $page->getCollection()->map(fn ($d) => [
                    'title' => $d->title,
                    'due_on' => $d->due_on?->toDateString(),
                    'date_precision' => $d->date_precision,
                    'date_label' => $d->date_label,
                    'status' => $d->deadline_status,
                    'confidence' => $d->confidence_level,
                    'description' => $d->description,
                    'jurisdiction' => $d->policyInstrument->jurisdiction->slug,
                    'policy' => $d->policyInstrument->slug,
                    'obligation' => $d->obligation?->slug,
                    'source_reference' => $d->source_reference,
                    'official_source_url' => $d->official_source_url,
                ])->all(),
                'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'license' => config('aipolicytracker.data_license'), 'license_url' => config('aipolicytracker.data_license_url')],
                'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl(), 'ics' => route('calendar.feed')],
            ];
        });
    }

    /** Incidents mirrored from the AI Incident Database. Attribution is a licence condition. */
    public function incidents(Request $request): JsonResponse
    {
        $filters = [
            'domain' => preg_match('/^[0-9]+$/', (string) $request->query('domain')) ? $request->query('domain') : null,
            'country' => preg_match('/^[A-Za-z .\-]{2,60}$/', (string) $request->query('country')) ? $request->query('country') : null,
            'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('from')) ? $request->query('from') : null,
        ];
        $perPage = min(max((int) $request->query('per_page', 50), 1), config('aipolicytracker.max_per_page'));
        $key = 'api.incidents.v3.'.hash('xxh128', json_encode($filters).$perPage.max(1, $request->integer('page', 1)));

        return $this->cached($key, function () use ($filters, $perPage) {
            $page = ExternalIncident::query()
                ->when($filters['domain'], fn ($q, $d) => $q->where('mit_domain', $d))
                // The same predicate RiskBrowseController uses for this column: it is a
                // json column, not jsonb, and matching the quoted token works identically
                // on SQLite and PostgreSQL where a containment operator does not.
                ->when($filters['country'], fn ($q, $c) => $q->where('countries', 'like', '%"'.$c.'"%'))
                ->when($filters['from'], fn ($q, $f) => $q->where('occurred_on', '>=', $f))
                // occurred_on repeats across incidents, so the key breaks the tie and keeps
                // pagination stable rather than letting rows shift between pages.
                ->orderByDesc('occurred_on')->orderByDesc('incident_id')->paginate($perPage)->withQueryString();

            return [
                'data' => $page->getCollection()->map(fn ($i) => [
                    'incident_id' => $i->incident_id,
                    'slug' => $i->slug,
                    'title' => $i->displayTitle(),
                    'description' => IncidentSensitivity::isSensitive($i) ? IncidentSensitivity::neutralDescription($i) : $i->description,
                    'occurred_on' => $i->occurred_on?->toDateString(),
                    'domain' => $i->mit_domain,
                    'subdomain' => $i->mit_subdomain,
                    'entity' => $i->entity,
                    'intent' => $i->intent,
                    'timing' => $i->timing,
                    'sectors' => $i->sectors,
                    'countries' => $i->countries,
                    'deployers' => $i->deployers,
                    'developers' => $i->developers,
                    'harmed' => $i->harmed,
                    'report_count' => $i->report_count,
                    // What this site adds: the policy angle.
                    'harm_domain' => $i->harm_domain,
                    'sensitivity' => $i->sensitivity ?? IncidentEnrichment::STANDARD,
                    'policy_angle' => $i->policy_angle,
                    'related_policies' => $i->relatedPolicies()->map(fn ($p) => ['slug' => $p->slug, 'title' => $p->short_title ?: $p->title, 'jurisdiction' => $p->jurisdiction?->slug, 'url' => $p->url()])->values()->all(),
                    'url' => $i->url(),
                ])->all(),
                'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'filters' => $filters] + $this->attribution($this->external->aiid()),
                'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
            ];
        });
    }

    /** Risk entries mirrored from the MIT AI Risk Repository. Attribution is a licence condition. */
    public function risks(Request $request): JsonResponse
    {
        $filters = [
            'domain' => ctype_digit((string) $request->query('domain')) ? (int) $request->query('domain') : null,
            'entity' => in_array($request->query('entity'), ExternalRisk::CAUSAL['entity'], true) ? $request->query('entity') : null,
            'intent' => in_array($request->query('intent'), ExternalRisk::CAUSAL['intent'], true) ? $request->query('intent') : null,
            'timing' => in_array($request->query('timing'), ExternalRisk::CAUSAL['timing'], true) ? $request->query('timing') : null,
        ];
        $perPage = min(max((int) $request->query('per_page', 50), 1), config('aipolicytracker.max_per_page'));
        $key = 'api.risks.v2.'.hash('xxh128', json_encode($filters).$perPage.max(1, $request->integer('page', 1)));

        return $this->cached($key, function () use ($filters, $perPage) {
            $query = ExternalRisk::query();
            foreach (['entity', 'intent', 'timing'] as $field) {
                if ($filters[$field]) {
                    $query->where($field, $filters[$field]);
                }
            }
            // external_risks is keyed by ev_id and has no id column. Ordering by a column
            // that does not exist is silently accepted by SQLite, which reads an unmatched
            // double-quoted identifier as a string literal, and rejected by PostgreSQL.
            $page = $query->when($filters['domain'], fn ($q, $d) => $q->where('domain', $d))
                ->orderBy('ev_id')->paginate($perPage)->withQueryString();

            return [
                'data' => $page->getCollection()->map(fn ($r) => [
                    'ev_id' => $r->ev_id,
                    'slug' => $r->slug,
                    'risk_category' => $r->risk_category,
                    'risk_subcategory' => $r->risk_subcategory,
                    'description' => $r->description,
                    'domain' => $r->domain,
                    'subdomain' => $r->subdomain,
                    'entity' => $r->entity,
                    'intent' => $r->intent,
                    'timing' => $r->timing,
                    'level' => $r->level,
                    'paper' => $r->quick_ref,
                    'citation' => PageTitle::citation($r->quick_ref),
                    'paper_title' => $r->paper_title,
                    'url' => $r->url(),
                ])->all(),
                'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'filters' => $filters] + $this->attribution($this->external->mitRisk()),
                'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
            ];
        });
    }

    /** One crosswalk row, shaped the same wherever it appears. */
    private function mapping(FrameworkMapping $mapping): array
    {
        $obligation = $mapping->obligation;

        return [
            'reference' => $mapping->reference,
            'families' => $mapping->families(),
            'note' => $mapping->note,
            'confidence' => $mapping->confidence_level,
            'obligation' => [
                'slug' => $obligation->slug,
                'title' => $obligation->title,
                'category' => $obligation->category,
                'is_binding' => (bool) $obligation->is_binding,
                'url' => $obligation->url(),
            ],
            'policy' => [
                'slug' => $obligation->policyInstrument->slug,
                'title' => $obligation->policyInstrument->short_title ?: $obligation->policyInstrument->title,
            ],
            'jurisdiction' => [
                'slug' => $obligation->policyInstrument->jurisdiction->slug,
                'name' => $obligation->policyInstrument->jurisdiction->name,
            ],
        ];
    }

    /**
     * Source, licence and citation for a mirrored dataset. Both upstream datasets are
     * shared under licences whose attribution requirement is a condition, not a courtesy,
     * so every response carrying their rows carries this too.
     */
    private function attribution(array $dataset): array
    {
        return ['source' => array_filter([
            'name' => $dataset['source'] ?? null,
            'url' => $dataset['source_url'] ?? null,
            'license' => $dataset['license'] ?? null,
            'license_url' => $dataset['license_url'] ?? null,
            'citation' => $dataset['citation'] ?? null,
            'snapshot_date' => $dataset['snapshot_date'] ?? null,
        ])];
    }

    private function cached(string $key, \Closure $callback): JsonResponse
    {
        return $this->respond(Cache::remember($key, 600, $callback));
    }

    private function respond(array $payload): JsonResponse
    {
        return response()->json($payload, 200, [
            'Cache-Control' => 'public, max-age=600, stale-while-revalidate=3600',
            'Access-Control-Allow-Origin' => '*',
            'X-Robots-Tag' => 'noindex',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
