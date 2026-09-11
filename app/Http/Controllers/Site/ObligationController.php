<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Obligation;
use App\Services\PolicyData\PolicyCatalog;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObligationController extends Controller
{
    public function index(Request $request, PolicyCatalog $catalog): View
    {
        $filters = $catalog->filtersFromRequest($request);
        $obligations = $catalog->obligationQuery($filters)->paginate(25)->withQueryString();
        $options = $catalog->filterOptions();
        $indexable = $catalog->isIndexableFilterSet($filters) && $obligations->currentPage() === 1;

        $title = 'AI compliance obligations explorer';
        $description = 'Search practical AI requirements such as risk management, data governance, transparency, human oversight, technical documentation, post-market monitoring and incident handling, with their legal sources and evidence examples.';
        if (! empty($filters['category']) && ! str_contains($filters['category'], ',')) {
            $cat = $options['categories']->firstWhere('slug', $filters['category']);
            if ($cat) {
                $title = $cat->name.': AI obligations across jurisdictions';
                $description = 'Legal requirements and voluntary guidance on '.mb_strtolower($cat->name).' for AI systems, with policy sources, actors and evidence examples.';
            }
        }

        $seo = Seo::make($title, $description, $catalog->canonicalFor(route('obligations.index'), $filters), $indexable)
            ->withBreadcrumbs([['Home', route('home')], ['Obligations', route('obligations.index')]])
            ->withJsonLd(['@type' => 'CollectionPage', 'name' => $title, 'url' => route('obligations.index'), 'isPartOf' => ['@id' => url('/').'#website']]);

        return view('site.obligations.index', compact('seo', 'obligations', 'filters', 'options'));
    }

    public function show(Obligation $obligation): View
    {
        abort_unless($obligation->published_at, 404);
        $obligation->load(['policyInstrument.jurisdiction', 'section', 'terms', 'frameworkMappings', 'evidenceArtifacts', 'applicabilityRules', 'deadlines']);
        $policy = $obligation->policyInstrument;
        $similar = Obligation::published()->with('policyInstrument.jurisdiction')->where('category', $obligation->category)->where('id', '!=', $obligation->id)->orderByDesc('is_binding')->limit(8)->get();
        $categoryName = \App\Models\TaxonomyTerm::where('taxonomy', 'obligation_category')->where('slug', $obligation->category)->value('name') ?? $obligation->category;

        $seo = Seo::make(
            $obligation->title.' ('.($policy->short_title ?: $policy->title).')',
            ($obligation->is_binding ? 'Legal requirement' : 'Voluntary guidance').' under '.($policy->short_title ?: $policy->title).' in '.$policy->jurisdiction->name.': what it requires, who it applies to, evidence examples and framework mappings.',
            $obligation->url(),
            filled($obligation->summary) && $policy->isIndexable()
        )->withBreadcrumbs([['Home', route('home')], ['Obligations', route('obligations.index')], [$obligation->title, $obligation->url()]])
            ->withModified($obligation->updated_at)
            ->withJsonLd([
                '@type' => 'WebPage',
                'name' => $obligation->title,
                'url' => $obligation->url(),
                'dateModified' => $obligation->updated_at?->toIso8601String(),
                'isPartOf' => ['@id' => url('/').'#website'],
                'about' => ['@type' => 'DefinedTerm', 'name' => $categoryName, 'inDefinedTermSet' => route('obligations.index')],
                'citation' => $obligation->official_source_url,
            ]);

        return view('site.obligations.show', compact('seo', 'obligation', 'policy', 'similar', 'categoryName'));
    }
}
