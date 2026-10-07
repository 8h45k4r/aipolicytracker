<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\TaxonomyTerm;
use App\Services\Hubs\HubCatalog;
use App\Services\Localization\Translations;
use App\Services\PolicyData\PolicyCatalog;
use App\Services\Records\AnswerBox;
use App\Services\Records\KeyFacts;
use App\Services\Records\QuestionBank;
use App\Support\Faq;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JurisdictionController extends Controller
{
    public function index(): View
    {
        $jurisdictions = Jurisdiction::published()
            ->withCount(['policyInstruments as policies_count' => fn ($q) => $q->published()])
            ->withCount(['policyInstruments as binding_in_force_count' => fn ($q) => $q->published()->where('is_binding', true)->whereIn('status', ['in_force', 'partially_applicable'])])
            ->withCount(['policyInstruments as strategy_count' => fn ($q) => $q->published()->where('instrument_type', 'strategy')])
            ->with('parent')
            ->orderBy('region')->orderBy('name')->get();
        $byRegion = $jurisdictions->groupBy('region');

        $seo = Seo::make(
            'AI Regulations Around the World: Map of AI Laws by Country',
            'AI policy map of the world: AI laws, national AI policies and guidance by country and region, with each one\'s status, binding rules versus guidance, deadlines and official sources.',
            route('jurisdictions.index')
        )->withBreadcrumbs([['Home', route('home')], ['Jurisdictions', route('jurisdictions.index')]])
            ->withPageType('CollectionPage', [
                'name' => 'AI regulations around the world',
                'mainEntity' => Seo::itemList($jurisdictions, fn ($j) => $j->name, fn ($j) => $j->url(), 'Jurisdictions with recorded AI policy'),
            ]);

        // The question people search for, answered from the records, ahead of the page's own.
        $withLaw = $jurisdictions->filter(fn ($j) => $j->binding_in_force_count > 0 && in_array($j->jurisdiction_type, ['country', 'supranational'], true));
        $strategies = $jurisdictions->filter(fn ($j) => $j->strategy_count > 0)->count();
        $computed = $withLaw->isEmpty() ? [] : [[
            'question' => 'Which countries have AI laws?',
            'answer' => $withLaw->count().' countries and supranational bodies have a binding AI instrument in force on record: '
                .$withLaw->sortByDesc(fn ($j) => ($j->jurisdiction_type === 'supranational' ? 1000 : 0) + $j->binding_in_force_count)->take(12)->pluck('name')->implode(', ')
                .($withLaw->count() > 12 ? ' and '.($withLaw->count() - 12).' more' : '')
                .'. A binding instrument here means an act, regulation, rule or order with legal force, which is not always an AI-specific act. '
                .$strategies.' jurisdictions have a national AI strategy or plan on record. Most still regulate AI through strategies, guidance or existing law rather than an AI act.',
        ]];
        $seo->withFaq(array_merge($computed, Faq::for('jurisdictions.index')));

        return view('site.jurisdictions.index', compact('seo', 'byRegion', 'jurisdictions'));
    }

    public function show(Jurisdiction $jurisdiction, PolicyCatalog $catalog): View|RedirectResponse
    {
        abort_unless($jurisdiction->published_at, 404);
        // A country with a hub has one address: the old one redirects to it.
        if ($jurisdiction->hubSlug() && request()->route()?->getName() === 'jurisdictions.show') {
            return redirect()->to($jurisdiction->url(), 301);
        }
        $jurisdiction->load(['parent', 'children' => fn ($q) => $q->published()]);
        $policies = $jurisdiction->policyInstruments()->published()->with(['terms', 'deadlines'])->orderByDesc('featured')->orderByDesc('is_binding')->orderBy('title')->get();
        $timeline = HubCatalog::timeline($policies);
        $region = HubCatalog::regionSlugFor($jurisdiction->region);
        $changes = ChangeEvent::published()->where('jurisdiction_id', $jurisdiction->id)->with('policyInstrument')->orderByDesc('occurred_on')->limit(8)->get();
        $deadlines = $catalog->upcomingDeadlines(8, $jurisdiction->id);
        $obligationCategories = Obligation::published()->whereIn('policy_instrument_id', $policies->pluck('id'))->selectRaw('category, COUNT(*) as n')->groupBy('category')->orderByDesc('n')->get();
        // Shown by the taxonomy's display name, not the raw key ("accuracy_robustness_security").
        $categoryNames = TaxonomyTerm::where('taxonomy', 'obligation_category')->pluck('name', 'slug');
        $obligationCategories->each(fn ($c) => $c->setAttribute('label', $categoryNames[$c->category] ?? str_replace('_', ' ', $c->category)));
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
        // A localised hub: our own summary in the reader's language, the facts as recorded.
        $locale = (string) request()->attributes->get('locale', '');
        $translation = $locale !== '' ? Translations::for($locale, 'hub:'.$jurisdiction->slug) : null;
        abort_if($locale !== '' && ! $translation, 404);
        $reviewedTranslation = Translations::isReviewed($translation);
        $englishUrl = $jurisdiction->url();
        $localUrl = $locale !== '' ? route('hubs.localized', ['locale' => $locale, 'hub' => $jurisdiction->hubSlug()]) : null;
        if ($translation) {
            $answer = $translation['answer'] ?? $answer;
        }
        $hreflang = [];
        if ($jurisdiction->hubSlug()) {
            $hreflang = ['en' => $englishUrl, 'x-default' => $englishUrl];
            foreach (Translations::availableFor('hub:'.$jurisdiction->slug) as $alt) {
                $hreflang[Translations::LOCALES[$alt['locale']]['hreflang']] = route('hubs.localized', ['locale' => $alt['locale'], 'hub' => $jurisdiction->hubSlug()]);
            }
        }
        $facts = KeyFacts::jurisdiction($jurisdiction, $policies, $deadlines, (int) $obligationCategories->sum('n'));

        $seo = Seo::make(
            $translation['title'] ?? PageTitle::jurisdiction($jurisdiction),
            $answer,
            $reviewedTranslation ? $localUrl : $englishUrl,
            $jurisdiction->isPageIndexable() && ($translation === null || $reviewedTranslation)
        )->withLanguage($locale !== '' ? $locale : 'en')->withHreflang($hreflang)->withBreadcrumbs(array_values(array_filter([['Home', route('home')], ['Jurisdictions', route('jurisdictions.index')], $region ? [$jurisdiction->region, HubCatalog::regionUrl($region)] : null, [$jurisdiction->name, $jurisdiction->url()]])))
            ->withFeed(route('updates.jurisdiction.feed', $jurisdiction->slug))
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

        return view('site.jurisdictions.show', compact('seo', 'jurisdiction', 'policies', 'changes', 'deadlines', 'obligationCategories', 'useCases', 'sectors', 'related', 'controls', 'answer', 'facts', 'timeline', 'region', 'translation', 'locale', 'reviewedTranslation', 'englishUrl'));
    }

    private function firstSentence(?string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        $pos = mb_strpos($text, '. ');

        return $pos ? mb_substr($text, 0, $pos + 1) : $text;
    }
}
