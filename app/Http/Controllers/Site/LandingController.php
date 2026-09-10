<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\PolicyCatalog;
use App\Support\Seo;
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
            ->withJsonLd([
                '@type' => 'Article',
                'headline' => $page['h1'],
                'description' => $page['description'],
                'url' => route('landing', $landing),
                'dateModified' => $lastModified?->toIso8601String(),
                'author' => ['@id' => url('/').'#organization'],
                'publisher' => ['@id' => url('/').'#organization'],
                'isPartOf' => ['@id' => url('/').'#website'],
            ]);
        if (! empty($page['faq'])) {
            $seo->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => collect($page['faq'])->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]])->values()->all()]);
        }

        return view('site.landing', compact('seo', 'page', 'landing', 'jurisdictions', 'policies', 'primary', 'changes', 'deadlines', 'obligations'));
    }

    public function guides(): View
    {
        $guides = collect(config('content.guides'))->map(fn ($g, $slug) => ['slug' => $slug] + $g)->values();
        $seo = Seo::make(
            'Guides: AI governance and regulation for startups and teams',
            'Practical, source-backed guides on EU AI Act readiness, AI governance for startups, and how ISO/IEC 42001 and the NIST AI RMF relate to the EU AI Act.',
            route('guides.index')
        )->withBreadcrumbs([['Home', route('home')], ['Guides', route('guides.index')]])
            ->withJsonLd(['@type' => 'CollectionPage', 'name' => 'Guides', 'url' => route('guides.index'), 'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $guides->map(fn ($g, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $g['h1'], 'url' => route('guides.show', $g['slug'])])->all()]]);

        return view('site.guides.index', compact('seo', 'guides'));
    }

    public function guide(string $slug): View
    {
        $page = config('content.guides.'.$slug);
        abort_unless($page, 404);
        $policies = PolicyInstrument::published()->with('jurisdiction')->whereIn('slug', $page['policies'] ?? [])->get();
        $obligations = Obligation::published()->with('policyInstrument.jurisdiction')->whereIn('slug', $page['obligations'] ?? [])->get();
        $frameworkObligations = ! empty($page['framework']) ? Obligation::published()->with(['policyInstrument.jurisdiction', 'frameworkMappings'])->whereHas('frameworkMappings', fn ($q) => $q->where('framework', $page['framework']))->orderBy('sort_order')->get() : collect();
        $lastModified = $policies->max('updated_at');

        $seo = Seo::make($page['title'], $page['description'], route('guides.show', $slug))
            ->withBreadcrumbs([['Home', route('home')], ['Guides', route('guides.index')], [$page['h1'], route('guides.show', $slug)]])
            ->withModified($lastModified)
            ->withOgType('article')
            ->withJsonLd(['@type' => 'Article', 'headline' => $page['h1'], 'description' => $page['description'], 'url' => route('guides.show', $slug), 'dateModified' => $lastModified?->toIso8601String(), 'author' => ['@id' => url('/').'#organization'], 'publisher' => ['@id' => url('/').'#organization'], 'isPartOf' => ['@id' => url('/').'#website']]);
        if (! empty($page['faq'])) {
            $seo->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => collect($page['faq'])->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]])->values()->all()]);
        }

        return view('site.guides.show', compact('seo', 'page', 'slug', 'policies', 'obligations', 'frameworkObligations'));
    }
}
