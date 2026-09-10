<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use App\Services\PolicyData\PolicyCatalog;
use App\Services\PolicyData\PolicySerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only public API (v1). Responses are cached and carry public cache headers.
 * Only published records are exposed; review and admin data never leave the server.
 */
class PublicApiController extends Controller
{
    public function __construct(private readonly PolicySerializer $serializer, private readonly PolicyCatalog $catalog) {}

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
                'changes' => route('api.v1.changes'),
                'taxonomies' => route('api.v1.taxonomies'),
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
        $key = 'api.policies.'.md5(json_encode($filters).$perPage.$request->query('page', 1));

        return $this->cached($key, function () use ($filters, $perPage) {
            $page = $this->catalog->policyQuery($filters)->paginate($perPage)->withQueryString();

            return [
                'data' => $page->getCollection()->map(fn ($p) => $this->serializer->policySummary($p))->all(),
                'meta' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'filters' => $filters],
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
        $key = 'api.obligations.'.md5(json_encode($filters).$perPage.$request->query('page', 1));

        return $this->cached($key, function () use ($filters, $perPage) {
            $page = $this->catalog->obligationQuery($filters)->paginate($perPage)->withQueryString();

            return [
                'data' => $page->getCollection()->map(fn ($o) => $this->serializer->obligation($o))->all(),
                'meta' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'filters' => $filters],
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

    public function changes(Request $request): JsonResponse
    {
        $since = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('since')) ? $request->query('since') : null;
        $jurisdiction = preg_match('/^[a-z0-9-]+$/', (string) $request->query('jurisdiction')) ? $request->query('jurisdiction') : null;
        $key = 'api.changes.'.md5($since.$jurisdiction.$request->query('page', 1));

        return $this->cached($key, function () use ($since, $jurisdiction) {
            $page = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])
                ->when($since, fn ($q) => $q->where('occurred_on', '>=', $since))
                ->when($jurisdiction, fn ($q) => $q->whereHas('jurisdiction', fn ($j) => $j->where('slug', $jurisdiction)))
                ->orderByDesc('occurred_on')->paginate(50)->withQueryString();

            return [
                'data' => $page->getCollection()->map(fn ($c) => $this->serializer->change($c))->all(),
                'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
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
