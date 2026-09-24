<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\Tool;
use App\Services\PolicyData\PolicyCatalog;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Editorial landing pages and guides. Editorial text lives in config/content.php;
 * every page is enriched with live records so it is never a static thin page.
 */
class LandingController extends Controller
{
    public function landing(string $landing, PolicyCatalog $catalog): View
    {
        $page = config('content.landings.'.$landing);
        abort_unless($page, 404);

        $jurisdictions = Jurisdiction::published()->whereIn('slug', $page['jurisdictions'])->orderBy('name')->get();
        $jurisdictionIds = $jurisdictions->pluck('id');
        $policies = PolicyInstrument::published()->with(['jurisdiction', 'terms'])->whereIn('jurisdiction_id', $jurisdictionIds)
            ->when(! empty($page['policy']), fn ($q) => $q->orderByRaw('CASE WHEN slug = ? THEN 0 ELSE 1 END', [$page['policy']]))
            ->orderByDesc('is_binding')->orderBy('title')->get();
        $primary = ! empty($page['policy']) ? $policies->firstWhere('slug', $page['policy']) : null;
        if ($primary) {
            $primary->load(['deadlines', 'obligations', 'sourceDocuments']);
        }
        $changes = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->whereIn('jurisdiction_id', $jurisdictionIds)->orderByDesc('occurred_on')->limit(6)->get();
        $deadlines = $jurisdictions->count() === 1 ? $catalog->upcomingDeadlines(6, $jurisdictions->first()->id) : $catalog->upcomingDeadlines(6);
        $obligations = Obligation::published()->with('policyInstrument')->whereIn('policy_instrument_id', $policies->pluck('id'))->orderByDesc('is_binding')->limit(10)->get();
        $indexable = $policies->isNotEmpty() && $jurisdictions->isNotEmpty();
        $lastModified = collect([$jurisdictions->max('updated_at'), $policies->max('updated_at')])->filter()->max();

        $seo = Seo::make($page['title'], $page['description'], route('landing', $landing), $indexable)
            ->withBreadcrumbs([['Home', route('home')], [$page['h1'], route('landing', $landing)]])
            ->withModified($lastModified)
            ->withOgType('article')
            ->withPageType('Article', [
                'headline' => $page['h1'],
                'author' => ['@id' => url('/').'#organization'],
            ]);
        if (! empty($page['steps'])) {
            $seo->withJsonLd(Seo::howTo($page['h1'], $page['description'], route('landing', $landing), $page['steps']));
        }
        if (! empty($page['faq'])) {
            $seo->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => collect($page['faq'])->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]])->values()->all()]);
        }

        return view('site.landing', compact('seo', 'page', 'landing', 'jurisdictions', 'policies', 'primary', 'changes', 'deadlines', 'obligations'));
    }

    public function guides(Request $request): View
    {
        $multi = fn (string $key, array $allowed) => array_values(array_intersect(array_map('strval', (array) $request->query($key, [])), array_keys($allowed)));
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => array_key_exists($request->query('type', ''), Tool::TYPES) ? $request->query('type') : null,
            'framework' => $multi('framework', config('resources.frameworks')),
            'topic' => $multi('topic', config('resources.topics')),
            'access' => in_array($request->query('access'), ['read', 'download'], true) ? $request->query('access') : null,
        ];
        $tools = Tool::published()->with('activeFiles')->orderBy('sort_order')->orderBy('title')->get()->map->card();
        $items = Tool::guideCards()->concat($tools)->filter(function ($i) use ($filters) {
            if ($filters['type'] && $i['type'] !== $filters['type']) {
                return false;
            }
            if ($filters['framework'] && array_intersect($filters['framework'], $i['frameworks']) === []) {
                return false;
            }
            if ($filters['topic'] && array_intersect($filters['topic'], $i['topics']) === []) {
                return false;
            }
            if ($filters['access'] === 'download' && empty($i['files'])) {
                return false;
            }
            if ($filters['access'] === 'read' && ! empty($i['files'])) {
                return false;
            }
            if ($filters['q'] !== '') {
                $hay = mb_strtolower($i['title'].' '.$i['short'].' '.implode(' ', $i['frameworks']).' '.implode(' ', $i['topics']).' '.$i['type']);
                foreach (preg_split('/\s+/', mb_strtolower($filters['q'])) as $term) {
                    if ($term !== '' && ! str_contains($hay, $term)) {
                        return false;
                    }
                }
            }

            return true;
        })->values();
        $filtered = array_filter($filters) !== [];
        $guides = $items->where('kind', 'guide')->values();
        $tools = $items->where('kind', 'tool')->values();

        $seo = Seo::make(
            'Guides and free tools: AI governance templates, checklists and registers',
            'Practical, source-backed guides plus free AI system inventory, risk register, EU AI Act readiness and incident response templates mapped to the EU AI Act, ISO/IEC 42001 and the NIST AI RMF.',
            route('guides.index')
        )->withBreadcrumbs([['Home', route('home')], ['Guides', route('guides.index')]])
            ->withPageType('CollectionPage', [
                'name' => 'Guides and free tools',
                'mainEntity' => Seo::itemList(
                    $items,
                    fn ($g) => $g['title'],
                    fn ($g) => $g['kind'] === 'tool' ? route('tools.show', $g['slug']) : route('guides.show', $g['slug']),
                    'Guides and free tools',
                ),
            ]);
        if ($filtered) {
            $seo->noindex(); // filter combinations are shareable but not indexable (thin/duplicate pages)
        }

        return view('site.guides.index', compact('seo', 'guides', 'tools', 'filters', 'filtered'));
    }

    public function guide(string $slug): View
    {
        abort_unless(preg_match('/^[a-z0-9-]{1,120}$/', $slug) === 1, 404);
        $page = config('content.guides.'.$slug);
        abort_unless($page, 404);
        $policies = PolicyInstrument::published()->with('jurisdiction')->whereIn('slug', $page['policies'] ?? [])->get();
        $obligations = Obligation::published()->with('policyInstrument.jurisdiction')->whereIn('slug', $page['obligations'] ?? [])->get();
        // Scoped to the jurisdiction the guide is about when it names one. Without that this
        // table repeated every mapping across every jurisdiction, which both contradicted the
        // guide's own title and duplicated /frameworks/{framework} wholesale.
        $frameworkObligations = ! empty($page['framework'])
            ? Obligation::published()->with(['policyInstrument.jurisdiction', 'frameworkMappings'])
                ->whereHas('frameworkMappings', fn ($q) => $q->where('framework', $page['framework']))
                ->when(! empty($page['framework_jurisdiction']), fn ($q) => $q->whereHas('policyInstrument.jurisdiction', fn ($j) => $j->where('slug', $page['framework_jurisdiction'])))
                ->orderBy('sort_order')->get()
            : collect();
        $lastModified = $policies->max('updated_at');

        $seo = Seo::make($page['title'], $page['description'], route('guides.show', $slug))
            ->withBreadcrumbs([['Home', route('home')], ['Guides', route('guides.index')], [$page['h1'], route('guides.show', $slug)]])
            ->withModified($lastModified)
            ->withOgType('article')
            ->withPageType('Article', ['headline' => $page['h1'], 'author' => ['@id' => url('/').'#organization']]);
        if (! empty($page['steps'])) {
            $seo->withJsonLd(Seo::howTo($page['h1'], $page['description'], route('guides.show', $slug), $page['steps']));
        }
        if (! empty($page['faq'])) {
            $seo->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => collect($page['faq'])->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]])->values()->all()]);
        }

        return view('site.guides.show', compact('seo', 'page', 'slug', 'policies', 'obligations', 'frameworkObligations'));
    }
}
