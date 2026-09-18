<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\PolicyCatalog;
use App\Services\PolicyData\PolicySerializer;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PolicyController extends Controller
{
    public function index(Request $request, PolicyCatalog $catalog): View
    {
        $filters = $catalog->filtersFromRequest($request);
        $policies = $catalog->policyQuery($filters)->paginate(20)->withQueryString();
        $options = $catalog->filterOptions();

        $indexable = $catalog->isIndexableFilterSet($filters) && $policies->currentPage() === 1;
        $title = 'AI policy explorer: laws, regulations, standards and guidance';
        $description = 'Search and filter source-backed AI policy instruments by jurisdiction, status, type, sector, use case, risk category and actor. Every record links to its official source.';
        if (! empty($filters['jurisdiction']) && ! str_contains($filters['jurisdiction'], ',')) {
            $j = $options['jurisdictions']->firstWhere('slug', $filters['jurisdiction']);
            if ($j) {
                $title = 'AI policies and regulations in '.$j->name;
                $description = 'Source-backed AI laws, regulations, standards and guidance in '.$j->name.', with status, deadlines and official sources.';
            }
        }
        if ($policies->currentPage() > 1) {
            $title .= ' (page '.$policies->currentPage().')';
        }

        $seo = Seo::make($title, $description, $catalog->canonicalFor(route('policies.index'), $filters).($policies->currentPage() > 1 && $indexable ? '?page='.$policies->currentPage() : ''), $indexable || ($policies->currentPage() > 1 && $catalog->isIndexableFilterSet($filters)))
            ->withBreadcrumbs([['Home', route('home')], ['Policies', route('policies.index')]])
            ->withJsonLd([
                '@type' => 'CollectionPage',
                'name' => $title,
                'url' => route('policies.index'),
                'isPartOf' => ['@id' => url('/').'#website'],
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'numberOfItems' => $policies->total(),
                    'itemListElement' => $policies->getCollection()->values()->map(fn ($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $p->title, 'url' => $p->url()])->all(),
                ],
            ]);

        return view('site.policies.index', compact('seo', 'policies', 'filters', 'options'));
    }

    public function show(PolicyInstrument $policy): View
    {
        abort_unless($policy->published_at, 404);
        $policy->load(['jurisdiction', 'terms', 'sections', 'obligations.terms', 'obligations.frameworkMappings', 'obligations.evidenceArtifacts', 'deadlines', 'versions', 'sourceDocuments', 'enforcementEvents', 'procurementRules', 'changeEvents', 'applicabilityRules']);

        $related = PolicyInstrument::published()->with('jurisdiction')->whereIn('slug', $policy->related_policies ?? [])->get();
        $useCases = $policy->termsOf('use_case')->pluck('slug')->all();
        $mit = app(\App\Services\ExternalData\ExternalDataset::class)->mitRisk();
        $aiid = app(\App\Services\ExternalData\ExternalDataset::class)->aiid();
        $risksAddressed = collect($mit['domains'] ?? [])->filter(fn ($d) => array_intersect($d['use_cases'] ?? [], $useCases) !== [])->map(fn ($d) => ['id' => $d['id'], 'name' => $d['name'], 'incidents' => $aiid['by_mit_domain'][$d['aiid_domain_label']] ?? 0])->values();
        $sameJurisdiction = PolicyInstrument::published()->where('jurisdiction_id', $policy->jurisdiction_id)->where('id', '!=', $policy->id)->orderBy('title')->limit(6)->get();
        $name = $policy->short_title ?: $policy->title;

        $seo = Seo::make(
            $name.': requirements, deadlines and compliance actions',
            'Source-backed guide to '.$name.' ('.$policy->jurisdiction->name.'): scope, status ('.$policy->statusEnum()->label().'), key dates, obligations, official sources and practical compliance actions.',
            $policy->url(),
            $policy->isIndexable()
        )->withBreadcrumbs([['Home', route('home')], ['Policies', route('policies.index')], [$name, $policy->url()]])
            ->withModified($policy->updated_at)
            ->withOgType('article')
            ->withJsonLd([
                '@type' => 'WebPage',
                'name' => $name.': requirements, deadlines and compliance actions',
                'url' => $policy->url(),
                'dateModified' => $policy->updated_at?->toIso8601String(),
                'isPartOf' => ['@id' => url('/').'#website'],
                'about' => [
                    '@type' => 'Legislation',
                    'name' => $policy->title,
                    'legislationJurisdiction' => $policy->jurisdiction->name,
                    'legislationType' => $policy->typeEnum()->label(),
                    'url' => $policy->official_source_url,
                    'datePublished' => $policy->published_on?->toDateString(),
                ],
                'citation' => $policy->sourceDocuments->map(fn ($s) => ['@type' => 'CreativeWork', 'name' => $s->title, 'url' => $s->url, 'publisher' => $s->publisher])->values()->all(),
            ]);
        // A binding instrument is described as Legislation as well as a page, so an answer
        // engine is told its jurisdiction, type, dates and — the part most often got wrong —
        // whether it is actually in force. Non-binding instruments get no such claim.
        if ($policy->is_binding) {
            $seo->withJsonLd(Seo::legislation($policy));
        }

        if (! empty($policy->faq)) {
            $seo->withJsonLd([
                '@type' => 'FAQPage',
                'mainEntity' => collect($policy->faq)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim($f['answer'])]])->values()->all(),
            ]);
        }

        return view('site.policies.show', compact('seo', 'policy', 'related', 'sameJurisdiction', 'risksAddressed'));
    }

    public function json(PolicyInstrument $policy, PolicySerializer $serializer): JsonResponse
    {
        abort_unless($policy->published_at, 404);

        return response()->json($serializer->policy($policy), 200, ['Cache-Control' => 'public, max-age=600'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
