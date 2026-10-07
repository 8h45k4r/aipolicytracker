<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use App\Services\Glossary\GlossaryTerms;
use App\Services\Search\PolicyRanker;
use App\Services\Templates\TemplateCatalog;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * One search box for the whole site, in the header of every page. Results are grouped by
 * what they are (jurisdictions, laws, duties, controls, templates, guides, glossary terms,
 * changes), with a link to the full filtered listing where one exists. Laws are ordered by
 * PolicyRanker so the record a reader names comes first; when one record is named plainly
 * (an exact short title, a country, a glossary term) it is shown as the top match above
 * the groups. /search/suggest answers the same query as a short JSON list for the
 * typeahead under the search boxes.
 * Never indexed: a results page is not content.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 6;

    private const SUGGESTIONS = 8;

    public function __construct(private readonly PolicyRanker $ranker) {}

    public function index(Request $request): View
    {
        $q = $this->query($request);
        $groups = [];
        $top = null;

        if ($q !== '') {
            $laws = $this->ranker->rank($q);
            $jurisdictions = $this->jurisdictions($q, self::PER_GROUP);
            $glossary = $this->glossary($q, self::PER_GROUP);
            $top = $this->topMatch($q, $laws, $jurisdictions, $glossary);

            $groups = array_values(array_filter([
                $this->group('Jurisdictions', $jurisdictions, route('jurisdictions.index')),
                $this->group('Laws and policies', $laws->take(self::PER_GROUP)->map(fn ($r) => $this->lawItem($r['policy'])), route('policies.index', ['q' => $q]), $laws->count()),
                $this->group('Obligations', $this->obligations($q, self::PER_GROUP), route('obligations.index', ['q' => $q]), Obligation::published()->search($q)->count()),
                $this->group('Controls', Control::published()->search($q)->orderByRaw($this->titleFirst('controls.title'), [PolicyRanker::like($q)])->orderBy('controls.title')->limit(self::PER_GROUP)->get()
                    ->map(fn ($c) => ['title' => $c->title, 'url' => $c->url(), 'meta' => $c->kindLabel()]), route('controls.index', ['q' => $q]), Control::published()->search($q)->count()),
                $this->group('Templates', $this->templates($q), route('templates.index')),
                $this->group('Guides', $this->guides($q), route('guides.index')),
                $this->group('Glossary', $glossary, route('glossary')),
                $this->group('Changes', ChangeEvent::published()->with('jurisdiction')->search($q)->where('slug', 'not like', 'template-%')->orderByDesc('occurred_on')->orderBy('title')->limit(self::PER_GROUP)->get()
                    ->map(fn ($c) => ['title' => $c->title, 'url' => $c->url(), 'meta' => $c->occurred_on->format('j M Y').' · '.$c->jurisdiction?->name]), route('changes.index')),
            ]));
        }

        $total = collect($groups)->sum('total');
        $seo = Seo::make($q === '' ? 'Search AI policy, obligations and templates' : 'Search: '.Str::limit($q, 40), 'Search laws, obligations, controls, templates, guides and glossary terms across every jurisdiction on record.', route('search'), false)
            ->noindex()
            ->withBreadcrumbs([['Home', route('home')], ['Search', route('search')]]);

        return view('site.search', compact('seo', 'q', 'groups', 'total', 'top'));
    }

    /**
     * GET /search/suggest?q=: up to eight {title, type, url} for the typeahead, best first.
     * Public and read-only, so it is cached like the API; fewer than two characters return
     * an empty list rather than the whole site.
     */
    public function suggest(Request $request): JsonResponse
    {
        $q = $this->query($request);
        $items = collect();

        if (mb_strlen($q) >= 2) {
            $laws = $this->ranker->rank($q);
            $jurisdictions = $this->jurisdictions($q, 2);
            $glossary = $this->glossary($q, 2);
            $top = $this->topMatch($q, $laws, $jurisdictions, $glossary);

            $items = collect($top ? [$top] : [])
                ->concat($laws->take(4)->map(fn ($r) => $this->lawItem($r['policy'])))
                ->concat($jurisdictions->map(fn ($i) => $i + ['type' => 'Jurisdiction']))
                ->concat($this->obligations($q, 2))
                ->concat($glossary->map(fn ($i) => $i + ['type' => 'Glossary']))
                ->concat($this->templates($q)->take(1)->map(fn ($i) => $i + ['type' => 'Template']))
                ->concat($this->guides($q)->take(1)->map(fn ($i) => $i + ['type' => 'Guide']))
                ->unique('url')->take(self::SUGGESTIONS)
                ->map(fn ($i) => ['title' => $i['title'], 'type' => $i['type'], 'url' => $i['url']])
                ->values();
        }

        return response()->json(['query' => $q, 'items' => $items, 'search_url' => route('search', ['q' => $q])], 200, [
            'Cache-Control' => 'public, max-age=600, stale-while-revalidate=3600',
            'X-Robots-Tag' => 'noindex',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function query(Request $request): string
    {
        $q = $request->query('q', '');

        return mb_substr(trim(is_string($q) ? $q : ''), 0, 120);
    }

    private function group(string $label, Collection $items, ?string $more, ?int $total = null): ?array
    {
        return $items->isEmpty() ? null : ['label' => $label, 'items' => $items->values()->all(), 'more' => $more, 'total' => $total ?? $items->count()];
    }

    private function lawItem(PolicyInstrument $p): array
    {
        return ['title' => $p->short_title ?: $p->title, 'url' => $p->url(), 'type' => 'Law or policy', 'meta' => $p->jurisdiction->name.' · '.$p->statusEnum()->label()];
    }

    /**
     * The one record the query names outright, if there is one: a law by its exact name,
     * then a jurisdiction by name or code, then a glossary term by name or acronym, then a
     * law that is the only best match by name.
     */
    private function topMatch(string $q, Collection $laws, Collection $jurisdictions, Collection $glossary): ?array
    {
        $needle = PolicyRanker::normalize($q);
        $first = $laws->first();

        if ($first && $first['tier'] === PolicyRanker::EXACT) {
            return $this->lawItem($first['policy']) + ['summary' => $first['policy']->summary_plain];
        }
        if ($jurisdiction = $jurisdictions->firstWhere('exact', true)) {
            return $jurisdiction + ['type' => 'Jurisdiction', 'summary' => null];
        }
        if ($term = $glossary->firstWhere('exact', true)) {
            return $term + ['type' => 'Glossary term', 'summary' => $term['definition']];
        }
        if ($needle !== '' && PolicyRanker::isClearWinner($laws)) {
            return $this->lawItem($first['policy']) + ['summary' => $first['policy']->summary_plain];
        }

        return null;
    }

    /** Rows whose title matches come before rows that match only in the body. */
    private function titleFirst(string $column): string
    {
        return "CASE WHEN LOWER({$column}) LIKE ? ESCAPE '\\' THEN 0 ELSE 1 END";
    }

    /** Duties whose title names the query first; among those, duties under laws in force before proposals. */
    private function obligations(string $q, int $limit): Collection
    {
        return Obligation::published()->with('policyInstrument.jurisdiction')->search($q)
            ->orderByRaw($this->titleFirst('obligations.title'), [PolicyRanker::like($q)])
            ->orderBy('obligations.title')->orderBy('obligations.id')
            ->limit(30)->get()
            ->sortBy(fn ($o) => [str_contains(mb_strtolower($o->title), mb_strtolower($q)) ? 0 : 1, $o->policyInstrument->statusEnum()->isBinding() ? 0 : 1, mb_strtolower($o->title)])
            ->take($limit)
            ->map(fn ($o) => ['title' => $o->title, 'url' => $o->url(), 'type' => 'Obligation', 'meta' => ($o->policyInstrument->short_title ?: $o->policyInstrument->title).($o->source_reference ? ' · '.$o->source_reference : '')])
            ->values();
    }

    /** Exact name or ISO code first, then names that start with the query, then the rest. */
    private function jurisdictions(string $q, int $limit): Collection
    {
        $like = PolicyRanker::like($q);
        $needle = mb_strtolower($q);

        return Jurisdiction::published()->where(fn ($w) => $w->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$like, '\\'])->orWhereRaw('LOWER(short_name) LIKE ? ESCAPE ?', [$like, '\\'])->orWhereRaw('LOWER(iso_code) = ?', [$needle]))
            ->get()
            ->map(fn ($j) => [$j, $this->closeness($q, array_filter([$j->name, $j->short_name, $j->iso_code]))])
            ->sortBy(fn ($pair) => [$pair[1], mb_strtolower($pair[0]->name)])
            ->take($limit)
            ->map(fn ($pair) => ['title' => $pair[0]->name, 'url' => $pair[0]->url(), 'meta' => $pair[0]->region, 'exact' => $pair[1] === 0])
            ->values();
    }

    /** 0 when a name equals the query, 1 when one starts with it, 2 otherwise. */
    private function closeness(string $q, array $names): int
    {
        $needle = PolicyRanker::normalize($q);
        $names = array_map(fn ($n) => PolicyRanker::normalize((string) $n), $names);

        return match (true) {
            in_array($needle, $names, true) => 0,
            collect($names)->contains(fn ($n) => str_starts_with($n, $needle)) => 1,
            default => 2,
        };
    }

    private function matches(string $q, string ...$texts): bool
    {
        $needle = mb_strtolower($q);

        return collect($texts)->contains(fn ($t) => str_contains(mb_strtolower($t), $needle));
    }

    private function templates(string $q): Collection
    {
        return TemplateCatalog::all()->filter(fn ($m) => $this->matches($q, $m['title'], $m['short'] ?? '', $m['seo_name'] ?? ''))
            ->take(self::PER_GROUP)->map(fn ($m) => ['title' => $m['title'], 'url' => TemplateCatalog::url($m['slug']), 'meta' => TemplateCatalog::formatList($m).' · free']);
    }

    private function guides(string $q): Collection
    {
        return collect(config('content.guides'))->filter(fn ($g) => $this->matches($q, $g['h1'], $g['summary'] ?? '', $g['description'] ?? ''))
            ->take(self::PER_GROUP)->map(fn ($g, $slug) => ['title' => $g['h1'], 'url' => route('guides.show', $slug), 'meta' => 'Guide']);
    }

    /**
     * Curated terms (each with its own page) and the defined vocabulary, as the glossary
     * page lists them. A term named exactly, by its name, the part before a slash, or the
     * acronym in brackets ("GPAI"), comes first; then names that contain the query; then
     * definitions that mention it.
     */
    private function glossary(string $q, int $limit): Collection
    {
        $terms = collect(GlossaryTerms::all())->map(fn ($t) => ['term' => $t['term'], 'definition' => $t['definition'], 'url' => GlossaryTerms::url($t['id'])])->values()
            ->concat(TaxonomyTerm::whereIn('taxonomy', ['actor', 'ai_system_type', 'risk_category', 'evidence_type'])->whereNotNull('description')->where('description', '!=', '')->orderBy('name')->get()
                ->map(fn ($t) => ['term' => $t->name, 'definition' => $t->description, 'url' => route('glossary').'#'.GlossaryController::anchor($t->taxonomy, $t->slug)]));

        $needle = PolicyRanker::normalize($q);

        return $terms->filter(fn ($t) => $this->matches($q, $t['term'], $t['definition']))
            ->map(fn ($t) => $t + ['exact' => $needle !== '' && in_array($needle, $this->termNames($t['term']), true)])
            ->sortBy(fn ($t) => [$t['exact'] ? 0 : ($this->matches($q, $t['term']) ? 1 : 2), mb_strtolower($t['term'])])
            ->take($limit)
            ->map(fn ($t) => ['title' => $t['term'], 'url' => $t['url'], 'meta' => Str::limit($t['definition'], 110), 'definition' => $t['definition'], 'exact' => $t['exact']])
            ->values();
    }

    /** "Deployer / user organisation" is named by "deployer"; "General-purpose AI model (GPAI)" by "gpai". */
    private function termNames(string $term): array
    {
        $names = [$term, GlossaryTerms::short($term), explode('/', $term)[0]];
        if (preg_match_all('/\(([^()]+)\)/u', $term, $m)) {
            array_push($names, ...$m[1]);
        }

        return array_values(array_unique(array_map(fn ($n) => PolicyRanker::normalize($n), $names)));
    }
}
