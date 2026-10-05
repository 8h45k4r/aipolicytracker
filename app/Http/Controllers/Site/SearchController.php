<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use App\Services\Templates\TemplateCatalog;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * One search box for the whole site, in the header of every page. Results are grouped by
 * what they are (jurisdictions, laws, duties, controls, templates, guides, glossary terms,
 * changes), title matches first, with a link to the full filtered listing where one exists.
 * Never indexed: a results page is not content.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 6;

    public function index(Request $request): View
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 120);
        $groups = $q === '' ? [] : array_values(array_filter([
            $this->group('Jurisdictions', $this->jurisdictions($q), route('jurisdictions.index')),
            $this->group('Laws and policies', PolicyInstrument::published()->with('jurisdiction')->search($q)->orderByRaw($this->titleFirst('policy_instruments.title'), [$this->like($q)])->limit(self::PER_GROUP)->get()
                ->map(fn ($p) => ['title' => $p->short_title ?: $p->title, 'url' => $p->url(), 'meta' => $p->jurisdiction->name.' · '.$p->statusEnum()->label()]), route('policies.index', ['q' => $q]), PolicyInstrument::published()->search($q)->count()),
            $this->group('Obligations', Obligation::published()->with('policyInstrument.jurisdiction')->search($q)->orderByRaw($this->titleFirst('obligations.title'), [$this->like($q)])->limit(self::PER_GROUP)->get()
                ->map(fn ($o) => ['title' => $o->title, 'url' => $o->url(), 'meta' => ($o->policyInstrument->short_title ?: $o->policyInstrument->title).($o->source_reference ? ' · '.$o->source_reference : '')]), route('obligations.index', ['q' => $q]), Obligation::published()->search($q)->count()),
            $this->group('Controls', Control::published()->search($q)->orderByRaw($this->titleFirst('controls.title'), [$this->like($q)])->limit(self::PER_GROUP)->get()
                ->map(fn ($c) => ['title' => $c->title, 'url' => $c->url(), 'meta' => $c->kindLabel()]), route('controls.index', ['q' => $q]), Control::published()->search($q)->count()),
            $this->group('Templates', $this->templates($q), route('templates.index')),
            $this->group('Guides', $this->guides($q), route('guides.index')),
            $this->group('Glossary', $this->glossary($q), route('glossary')),
            $this->group('Changes', ChangeEvent::published()->with('jurisdiction')->search($q)->where('slug', 'not like', 'template-%')->orderByDesc('occurred_on')->limit(self::PER_GROUP)->get()
                ->map(fn ($c) => ['title' => $c->title, 'url' => $c->url(), 'meta' => $c->occurred_on->format('j M Y').' · '.$c->jurisdiction?->name]), route('changes.index')),
        ]));

        $total = collect($groups)->sum('total');
        $seo = Seo::make($q === '' ? 'Search AI policy, obligations and templates' : 'Search: '.Str::limit($q, 40), 'Search laws, obligations, controls, templates, guides and glossary terms across every jurisdiction on record.', route('search'), false)
            ->noindex()
            ->withBreadcrumbs([['Home', route('home')], ['Search', route('search')]]);

        return view('site.search', compact('seo', 'q', 'groups', 'total'));
    }

    private function group(string $label, Collection $items, ?string $more, ?int $total = null): ?array
    {
        return $items->isEmpty() ? null : ['label' => $label, 'items' => $items->values()->all(), 'more' => $more, 'total' => $total ?? $items->count()];
    }

    private function like(string $q): string
    {
        return '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($q)).'%';
    }

    /** Rows whose title matches come before rows that match only in the body. */
    private function titleFirst(string $column): string
    {
        return "CASE WHEN LOWER({$column}) LIKE ? ESCAPE '\\' THEN 0 ELSE 1 END";
    }

    private function jurisdictions(string $q): Collection
    {
        $like = $this->like($q);

        return Jurisdiction::published()->where(fn ($w) => $w->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$like, '\\'])->orWhereRaw('LOWER(short_name) LIKE ? ESCAPE ?', [$like, '\\'])->orWhereRaw('LOWER(iso_code) = ?', [mb_strtolower($q)]))
            ->orderBy('name')->limit(self::PER_GROUP)->get()
            ->map(fn ($j) => ['title' => $j->name, 'url' => $j->url(), 'meta' => $j->region]);
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

    /** Curated terms and the defined vocabulary, as the glossary page lists them; a name match ranks above a definition match. */
    private function glossary(string $q): Collection
    {
        $terms = collect(config('glossary.terms'))->map(fn ($t, $id) => ['id' => $id, 'term' => $t['term'], 'definition' => $t['definition']])->values()
            ->concat(TaxonomyTerm::whereIn('taxonomy', ['actor', 'ai_system_type', 'risk_category', 'evidence_type'])->whereNotNull('description')->where('description', '!=', '')->get()
                ->map(fn ($t) => ['id' => GlossaryController::anchor($t->taxonomy, $t->slug), 'term' => $t->name, 'definition' => $t->description]));

        return $terms->filter(fn ($t) => $this->matches($q, $t['term'], $t['definition']))
            ->sortBy(fn ($t) => $this->matches($q, $t['term']) ? 0 : 1)
            ->take(self::PER_GROUP)->map(fn ($t) => ['title' => $t['term'], 'url' => route('glossary').'#'.$t['id'], 'meta' => Str::limit($t['definition'], 110)]);
    }
}
