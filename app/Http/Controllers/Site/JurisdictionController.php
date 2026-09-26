<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Services\PolicyData\PolicyCatalog;
use App\Services\Records\AnswerBox;
use App\Services\Records\KeyFacts;
use App\Services\Records\QuestionBank;
use App\Support\PageTitle;
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
            ->withPageType('CollectionPage', [
                'name' => 'AI regulation by country and region',
                'mainEntity' => Seo::itemList($jurisdictions, fn ($j) => $j->name, fn ($j) => $j->url(), 'Jurisdictions with recorded AI policy'),
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
        // What an organisation operates to meet this jurisdiction's duties, counted by
        // how many of them each control satisfies.
        $controls = Obligation::published()->whereIn('policy_instrument_id', $policies->pluck('id'))->with('controls')->get()
            ->flatMap(fn ($o) => $o->controls->filter(fn ($c) => $c->published_at)->map(fn ($c) => ['control' => $c, 'satisfies' => $c->pivot->relationship === 'satisfies']))
            ->groupBy(fn ($r) => $r['control']->slug)
            ->map(fn ($rows) => ['control' => $rows->first()['control'], 'satisfies' => $rows->where('satisfies', true)->count(), 'duties' => $rows->count()])
            ->sortByDesc(fn ($r) => [$r['satisfies'], $r['duties']])->values();

        $answer = AnswerBox::jurisdiction($jurisdiction, $policies, $deadlines);
        $facts = KeyFacts::jurisdiction($jurisdiction, $policies, $deadlines, (int) $obligationCategories->sum('n'));

        $seo = Seo::make(
            PageTitle::jurisdiction($jurisdiction),
            $answer,
            $jurisdiction->url(),
            $jurisdiction->isIndexable()
        )->withBreadcrumbs([['Home', route('home')], ['Jurisdictions', route('jurisdictions.index')], [$jurisdiction->name, $jurisdiction->url()]])
            ->withModified($lastModified)
            ->withPublished($jurisdiction->created_at)
            ->withCard('jurisdiction', $jurisdiction->slug, $lastModified)
            ->withAlternate('text/markdown', route('jurisdictions.context', $jurisdiction->slug))
            ->withPageProperties(Seo::provenance($jurisdiction))
            ->withPageType('CollectionPage', [
                'name' => 'AI regulation in '.$jurisdiction->nameWithArticle(),
                'description' => $answer,
                'about' => ['@type' => $jurisdiction->jurisdiction_type === 'supranational' ? 'AdministrativeArea' : ($jurisdiction->jurisdiction_type === 'state' ? 'State' : 'Country'), 'name' => $jurisdiction->name],
                'mainEntity' => Seo::itemList($policies, fn ($p) => $p->title, fn ($p) => $p->url(), 'AI policy instruments recorded for '.$jurisdiction->name),
            ])
            ->withJsonLd(Seo::dataset(
                'AI regulation in '.$jurisdiction->name,
                'Recorded AI policy instruments, obligations and deadlines for '.$jurisdiction->name.', each linked to its official source.',
                $jurisdiction->url(),
                ['text/markdown' => route('jurisdictions.context', $jurisdiction->slug)],
                $lastModified,
            ));
        $seo->withFaq(QuestionBank::jurisdiction($jurisdiction, $policies, $deadlines));

        return view('site.jurisdictions.show', compact('seo', 'jurisdiction', 'policies', 'changes', 'deadlines', 'obligationCategories', 'useCases', 'sectors', 'related', 'controls', 'answer', 'facts'));
    }

    private function firstSentence(?string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        $pos = mb_strpos($text, '. ');

        return $pos ? mb_substr($text, 0, $pos + 1) : $text;
    }
}
