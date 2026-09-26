<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\TransitionIndicator;
use App\Models\TransitionMeasure;
use App\Services\Transition\DisplacementPolicyIndex;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The AI economic transition tracker: measures (dividends, basic income,
 * AI taxes, funds, layoff disclosure, retraining, worker voice), indicators
 * and the displacement policy index. Everything on these pages is computed
 * from the records; a draft is shown as a draft and never indexed.
 */
class TransitionController extends Controller
{
    public const LANDINGS = [
        'ubi' => ['type' => 'universal_basic_income', 'h1' => 'Universal basic income and AI', 'title' => 'UBI and AI: Basic Income Proposals Tracked', 'lead' => 'Basic-income proposals, pilots and laws framed as a response to AI-driven job displacement, one record each, with status, mechanism, funding and the official source once verified.'],
        'ai-dividend' => ['type' => 'ai_dividend', 'h1' => 'AI dividend proposals', 'title' => 'AI Dividend Proposals: Who Pays, Who Receives', 'lead' => 'Proposals to share the gains of AI directly with residents: their mechanism, funding, trigger and benefit, as recorded from official sources.'],
        'ai-tax' => ['type' => 'ai_tax', 'h1' => 'AI taxes and levies', 'title' => 'AI Tax Proposals: Excise, Automation and Robot Taxes', 'lead' => 'Bills and laws that tax AI systems, automation or the revenue they generate, with bill numbers, sponsors and status once verified.'],
        'ai-layoff-disclosure-laws' => ['type' => 'layoff_disclosure', 'h1' => 'AI layoff disclosure laws', 'title' => 'AI Layoff Disclosure Laws: Notice Duties Tracked', 'lead' => 'Duties to disclose when a layoff is attributable to AI or automation, in notice forms or statute, as recorded from official sources.'],
    ];

    public const MIN_INDEXABLE_LANDING = 3;

    public function index(Request $request, ?string $landing = null): View
    {
        $page = $landing ? (self::LANDINGS[$landing] ?? abort(404)) : null;
        $filters = [
            'type' => $page['type'] ?? (array_key_exists((string) $request->query('type'), TransitionMeasure::TYPES) ? $request->query('type') : null),
            'status' => array_key_exists((string) $request->query('status'), TransitionMeasure::STATUSES) ? $request->query('status') : null,
            'jurisdiction' => preg_match('/^[a-z0-9-]+$/', (string) $request->query('jurisdiction')) ? $request->query('jurisdiction') : null,
        ];
        $all = TransitionMeasure::whereNotNull('published_at')->with('jurisdiction')->orderByDesc('last_verified_at')->orderBy('title')->get();
        $measures = $all->filter(fn ($m) => (! $filters['type'] || $m->measure_type === $filters['type']) && (! $filters['status'] || $m->status === $filters['status']) && (! $filters['jurisdiction'] || $m->jurisdiction->slug === $filters['jurisdiction']))->values();
        // Three states, named honestly: verified by a named reviewer, recorded from a cited
        // source but awaiting that review, or an empty draft. Only the first is "verified".
        $recorded = $measures->filter(fn ($m) => ! $m->isDraft());
        $verified = $recorded->filter(fn ($m) => $m->review_status === 'verified');
        $indicators = TransitionIndicator::whereNotNull('published_at')->with('jurisdiction')->orderBy('title')->get();
        $index = DisplacementPolicyIndex::latest();
        $byType = $measures->groupBy('measure_type')->map->count()->mapWithKeys(fn ($n, $t) => [TransitionMeasure::TYPES[$t] ?? $t => $n])->all();
        $byStatus = $measures->groupBy('status')->map->count()->mapWithKeys(fn ($n, $s) => [TransitionMeasure::STATUSES[$s] ?? $s => $n])->all();
        $timeline = $recorded->flatMap(fn ($m) => collect(['introduced_on' => 'introduced', 'enacted_on' => 'enacted', 'in_force_on' => 'in force'])->filter(fn ($l, $f) => $m->{$f})->map(fn ($l, $f) => ['date' => $m->{$f}, 'label' => $m->title.' '.$l, 'measure' => $m]))->sortBy(fn ($e) => $e['date']->timestamp)->values();
        $arguments = ['for' => $recorded->flatMap(fn ($m) => collect($m->arguments_for ?? [])->map(fn ($a) => $a + ['measure' => $m]))->values(), 'against' => $recorded->flatMap(fn ($m) => collect($m->arguments_against ?? [])->map(fn ($a) => $a + ['measure' => $m]))->values()];
        $jurisdictions = $all->pluck('jurisdiction')->unique('id')->sortBy('name')->values();

        $answer = sprintf(
            '%d %s recorded%s: %d verified against an official source by a named reviewer, %d recorded from a cited source and awaiting that review, and %d still in draft. Types recorded: %s. The displacement policy index scores %d %s this quarter from verified measures only; a pending or draft record counts for nothing until a reviewer has read the source.',
            $measures->count(), Str::plural('measure', $measures->count()), $page ? ' for '.mb_strtolower($page['h1']) : ' across '.$jurisdictions->count().' '.Str::plural('jurisdiction', $jurisdictions->count()),
            $verified->count(), $recorded->count() - $verified->count(), $measures->count() - $recorded->count(),
            $byType === [] ? 'none yet' : collect($byType)->map(fn ($n, $t) => mb_strtolower($t).' ('.$n.')')->join(', '),
            $index->count(), Str::plural('jurisdiction', $index->count())
        );
        $faq = [
            ['question' => 'What counts as an AI economic transition measure?', 'answer' => 'A proposal, bill, pilot or law that responds to AI-driven economic change: an AI dividend, a basic income, an AI or automation tax, a sovereign wealth fund, a duty to disclose AI-attributed layoffs, retraining funding, or worker-voice provisions. Each record carries mechanism, funding, trigger, benefit, cost, bill number and sponsors, all null until read from the official source.'],
            ['question' => 'Why are some records drafts?', 'answer' => 'A record starts as a draft with every factual field empty. It becomes a record only when a reviewer has read the official source and filled the fields; until then the page says so, the record is not indexed, and it does not count in the index.'],
            ['question' => 'How is the displacement policy index computed?', 'answer' => DisplacementPolicyIndex::explain()['rule'].' The weights are published on the methodology page and versioned ('.DisplacementPolicyIndex::VERSION.').'],
            ['question' => 'Are the arguments for and against the tracker\'s own view?', 'answer' => 'No. Every argument is attributed to the person or body that made it and links to where it was made. The tracker records positions; it does not hold one.'],
        ];
        $title = $page ? $page['title'] : 'AI Economic Transition Tracker: UBI, Dividends, Taxes, Layoffs';
        $url = $landing ? route('transition.landing', $landing) : route('transition.index');
        $indexable = $page ? $verified->count() >= self::MIN_INDEXABLE_LANDING : (! $filters['type'] && ! $filters['status'] && ! $filters['jurisdiction']);
        $seo = Seo::make(PageTitle::fit($title), PageTitle::description($answer), $url, $indexable)
            ->withBreadcrumbs(array_values(array_filter([['Home', route('home')], ['AI economic transition', route('transition.index')], $page ? [$page['h1'], $url] : null])))
            ->withModified($all->max('updated_at'))
            ->withPageType('CollectionPage', ['name' => $title, 'description' => $answer, 'mainEntity' => Seo::itemList($measures, fn ($m) => $m->title, fn ($m) => $m->url(), 'AI economic transition measures')])
            ->withJsonLd(Seo::dataset('AI economic transition measures', 'Proposals, bills, pilots and laws responding to AI-driven economic change, with status, mechanism, funding and sources.', route('transition.index'), ['application/json' => route('api.v1.transition.measures')], $all->max('updated_at')))
            ->withFaq($faq);
        if (! $indexable) {
            $seo->noindex();
        }

        return view('site.transition.index', compact('seo', 'page', 'landing', 'filters', 'measures', 'verified', 'indicators', 'index', 'byType', 'byStatus', 'timeline', 'arguments', 'jurisdictions', 'answer'));
    }

    public function landing(string $theme): View
    {
        return $this->index(request(), $theme);
    }

    public function show(TransitionMeasure $measure): View
    {
        abort_unless($measure->published_at, 404);
        $measure->load('jurisdiction');
        $related = TransitionMeasure::whereNotNull('published_at')->where('id', '!=', $measure->id)->where(fn ($q) => $q->where('measure_type', $measure->measure_type)->orWhere('jurisdiction_id', $measure->jurisdiction_id))->with('jurisdiction')->limit(6)->get();
        $index = DisplacementPolicyIndex::latest()->firstWhere('jurisdiction_id', $measure->jurisdiction_id);
        $facts = array_filter([
            'Type' => $measure->typeLabel(), 'Status' => $measure->statusLabel(), 'Jurisdiction' => $measure->jurisdiction->name,
            'Bill number' => $measure->bill_number, 'Sponsors' => $measure->sponsors ? implode(', ', $measure->sponsors) : null,
            'Introduced' => $measure->introduced_on?->format('j F Y'), 'Enacted' => $measure->enacted_on?->format('j F Y'), 'In force' => $measure->in_force_on?->format('j F Y'),
            'Review status' => $measure->isDraft() ? 'Draft: not yet verified' : str_replace('_', ' ', $measure->review_status),
        ]);
        $answer = $measure->isDraft()
            ? sprintf('%s is a draft record of a %s in %s. Nothing about it has yet been read from an official source, so its mechanism, funding, sponsors, dates and amounts are empty rather than guessed. It is listed so that the gap is visible; it becomes a record when a reviewer verifies it.', $measure->title, mb_strtolower($measure->typeLabel()), $measure->jurisdiction->nameWithArticle())
            : trim((string) $measure->summary);
        $seo = Seo::make(PageTitle::fit($measure->title, [': '.$measure->typeLabel().' ('.($measure->jurisdiction->short_name ?: $measure->jurisdiction->name).')', '']), PageTitle::description($answer), $measure->url(), $measure->isIndexable())
            ->withBreadcrumbs([['Home', route('home')], ['AI economic transition', route('transition.index')], [$measure->title, $measure->url()]])
            ->withModified($measure->updated_at)
            ->withPageProperties(Seo::provenance($measure))
            ->withPageType('Article', ['headline' => $measure->title, 'about' => ['@type' => 'Legislation', 'name' => $measure->title, 'legislationJurisdiction' => $measure->jurisdiction->name]])
            ->withFaq(array_values(array_filter([
                ['question' => 'What is the status of '.$measure->title.'?', 'answer' => $measure->isDraft() ? 'Unverified: the record is a draft and the status has not been read from an official source.' : $measure->statusLabel().($measure->in_force_on ? ', in force since '.$measure->in_force_on->format('j F Y') : ($measure->enacted_on ? ', enacted '.$measure->enacted_on->format('j F Y') : ''))],
                $measure->mechanism ? ['question' => 'How would it work?', 'answer' => $measure->mechanism] : null,
                $measure->funding ? ['question' => 'How is it funded?', 'answer' => $measure->funding] : null,
            ])));
        if (! $measure->isIndexable()) {
            $seo->noindex();
        }

        return view('site.transition.show', compact('seo', 'measure', 'related', 'index', 'facts', 'answer'));
    }

    public function methodology(): View
    {
        $explain = DisplacementPolicyIndex::explain();
        $index = DisplacementPolicyIndex::latest();
        $seo = Seo::make('Displacement Policy Index: Method, Weights and Versions', 'How the displacement policy index scores a jurisdiction, 0 to 100, from its verified AI economic transition measures: four dimensions, published weights, a versioned pure function, and why drafts count for nothing.', route('transition.methodology'))
            ->withBreadcrumbs([['Home', route('home')], ['AI economic transition', route('transition.index')], ['Index methodology', route('transition.methodology')]])
            ->withPageType('Article', ['headline' => 'Displacement policy index methodology'])
            ->withFaq([
                ['question' => 'What is the maximum score?', 'answer' => '100: four dimensions of 25. A jurisdiction with an in-force measure in every dimension scores 100; one with only proposals scores at most 32.'],
                ['question' => 'Why is the function versioned?', 'answer' => 'So that a score can be reproduced. Every snapshot stores the version, the inputs and the quarter; changing a weight is a new version, and old snapshots keep theirs.'],
                ['question' => 'Why do drafts count for nothing?', 'answer' => 'Because a draft has not been read from an official source. Scoring it would score a rumour.'],
            ]);

        return view('site.transition.methodology', compact('seo', 'explain', 'index'));
    }
}
