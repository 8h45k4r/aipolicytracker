<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Services\PolicyData\PolicyCatalog;
use App\Support\Seo;
use Illuminate\View\View;

class JurisdictionController extends Controller
{
    public function index(): View
    {
        $jurisdictions = Jurisdiction::published()
            ->withCount(['policyInstruments as policies_count' => fn ($q) => $q->published()])
            ->with('parent')
            ->orderBy('region')->orderBy('name')->get();
        $byRegion = $jurisdictions->groupBy('region');

        $seo = Seo::make(
            'AI regulation by country and region',
            'Directory of jurisdictions with source-backed AI laws, strategies and guidance: current regulatory status, binding rules versus guidance, deadlines and official sources.',
            route('jurisdictions.index')
        )->withBreadcrumbs([['Home', route('home')], ['Jurisdictions', route('jurisdictions.index')]])
            ->withJsonLd([
                '@type' => 'CollectionPage',
                'name' => 'AI regulation by country and region',
                'url' => route('jurisdictions.index'),
                'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $jurisdictions->values()->map(fn ($j, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $j->name, 'url' => $j->url()])->all()],
            ]);

        return view('site.jurisdictions.index', compact('seo', 'byRegion', 'jurisdictions'));
    }

    public function show(Jurisdiction $jurisdiction, PolicyCatalog $catalog): View
    {
        abort_unless($jurisdiction->published_at, 404);
        $jurisdiction->load(['parent', 'children' => fn ($q) => $q->published()]);
        $policies = $jurisdiction->policyInstruments()->published()->with('terms')->orderByDesc('featured')->orderByDesc('is_binding')->orderBy('title')->get();
        $changes = ChangeEvent::published()->where('jurisdiction_id', $jurisdiction->id)->with('policyInstrument')->orderByDesc('occurred_on')->limit(8)->get();
        $deadlines = $catalog->upcomingDeadlines(8, $jurisdiction->id);
        $obligationCategories = Obligation::published()->whereIn('policy_instrument_id', $policies->pluck('id'))->selectRaw('category, COUNT(*) as n')->groupBy('category')->orderByDesc('n')->get();
        $useCases = $policies->flatMap(fn ($p) => $p->terms->where('taxonomy', 'use_case'))->unique('slug')->sortBy('name')->values();
        $sectors = $policies->flatMap(fn ($p) => $p->terms->where('taxonomy', 'sector'))->unique('slug')->sortBy('name')->values();
        $related = Jurisdiction::published()->whereIn('slug', $jurisdiction->related_jurisdictions ?? [])->orderBy('name')->get();
        $lastModified = collect([$jurisdiction->updated_at, $policies->max('updated_at'), $changes->max('updated_at')])->filter()->max();

        $seo = Seo::make(
            'AI regulation in '.$jurisdiction->nameWithArticle().': laws, status and deadlines',
            'AI regulation in '.$jurisdiction->nameWithArticle().': '.$this->firstSentence($jurisdiction->regulatory_status_summary).' Official sources, obligations and upcoming deadlines.',
            $jurisdiction->url(),
            $jurisdiction->isIndexable()
        )->withBreadcrumbs([['Home', route('home')], ['Jurisdictions', route('jurisdictions.index')], [$jurisdiction->name, $jurisdiction->url()]])
            ->withModified($lastModified)
            ->withJsonLd([
                '@type' => 'WebPage',
                'name' => 'AI regulation in '.$jurisdiction->nameWithArticle(),
                'url' => $jurisdiction->url(),
                'dateModified' => $lastModified?->toIso8601String(),
                'isPartOf' => ['@id' => url('/').'#website'],
                'about' => ['@type' => $jurisdiction->jurisdiction_type === 'supranational' ? 'AdministrativeArea' : ($jurisdiction->jurisdiction_type === 'state' ? 'State' : 'Country'), 'name' => $jurisdiction->name],
                'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $policies->values()->map(fn ($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $p->title, 'url' => $p->url()])->all()],
            ]);
        if (! empty($jurisdiction->faq)) {
            $seo->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => collect($jurisdiction->faq)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim($f['answer'])]])->values()->all()]);
        }

        return view('site.jurisdictions.show', compact('seo', 'jurisdiction', 'policies', 'changes', 'deadlines', 'obligationCategories', 'useCases', 'sectors', 'related'));
    }

    private function firstSentence(?string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        $pos = mb_strpos($text, '. ');

        return $pos ? mb_substr($text, 0, $pos + 1) : $text;
    }
}
