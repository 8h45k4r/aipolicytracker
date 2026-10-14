<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\TaxonomyTerm;
use App\Services\Glossary\GlossaryTerms;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * /glossary: the terms the records use, each with a stable anchor (/glossary#deployer)
 * that answer engines can cite and record pages can link to.
 *
 * Two sources: the curated explanations in config/glossary.php, and the defined
 * vocabulary in data/taxonomies (actors, system types, risk categories, evidence types),
 * read from the database so a definition is written once.
 */
class GlossaryController extends Controller
{
    /** Taxonomies whose terms carry definitions, with the page's heading for each and the obligations filter that lists them. */
    private const TAXONOMIES = [
        'actor' => ['Who does what', 'actor'],
        'ai_system_type' => ['Kinds of AI system', null],
        'risk_category' => ['Risk categories', 'risk'],
        'evidence_type' => ['Kinds of compliance evidence', null],
    ];

    /** The order the curated groups are listed in; a group not named here follows them. */
    private const GROUP_ORDER = ['Governance, risk and compliance', 'AI security', GlossaryTerms::DEFAULT_GROUP];

    public function index(): View
    {
        $groups = collect($this->curated())->groupBy('group')
            ->sortBy(fn ($terms, $group) => array_search($group, self::GROUP_ORDER, true) === false ? 99 : array_search($group, self::GROUP_ORDER, true))
            ->map(fn ($terms, $group) => ['heading' => $group, 'id' => Str::slug($group), 'terms' => $terms->values()->all()])
            ->values()->all();
        $vocabulary = TaxonomyTerm::whereIn('taxonomy', array_keys(self::TAXONOMIES))->whereNotNull('description')->where('description', '!=', '')
            ->orderBy('sort_order')->orderBy('name')->get()->groupBy('taxonomy');
        foreach (self::TAXONOMIES as $taxonomy => [$heading, $filter]) {
            $terms = ($vocabulary[$taxonomy] ?? collect())->map(fn (TaxonomyTerm $t) => [
                'id' => self::anchor($taxonomy, $t->slug),
                'term' => $t->name,
                'definition' => $t->description,
                'source' => null,
                'page' => null,
                'see' => $filter ? [['Obligations that apply to this', route('obligations.index', [$filter => $t->slug])]] : [],
            ])->values()->all();
            if ($terms !== []) {
                $groups[] = ['heading' => $heading, 'id' => str_replace('_', '-', $taxonomy), 'terms' => $terms];
            }
        }

        $all = collect($groups)->flatMap(fn ($g) => $g['terms']);
        $setId = route('glossary').'#terms';

        $seo = Seo::make(
            'AI governance glossary: '.$all->count().' terms explained',
            'AI governance, risk and compliance glossary: AI GRC, AI risk, the EU AI Act, ISO/IEC 42001, NIST AI RMF, SAIF, OWASP LLM Top 10, prompt injection and more, each sourced.',
            route('glossary')
        )->withBreadcrumbs([['Home', route('home')], ['Glossary', route('glossary')]])
            ->withPageType('WebPage', ['name' => 'AI governance glossary', 'mainEntity' => ['@id' => $setId]])
            ->withJsonLd([
                '@type' => 'DefinedTermSet',
                '@id' => $setId,
                'name' => 'AI governance glossary',
                'url' => route('glossary'),
                'publisher' => ['@id' => url('/').'#organization'],
                'hasDefinedTerm' => $all->map(fn ($t) => array_filter([
                    '@type' => 'DefinedTerm',
                    '@id' => route('glossary').'#'.$t['id'],
                    'name' => $t['term'],
                    'termCode' => $t['id'],
                    'description' => $t['definition'],
                    'url' => $t['page'] ?? route('glossary').'#'.$t['id'],
                    'inDefinedTermSet' => $setId,
                    'subjectOf' => $t['source'] ? ['@type' => 'CreativeWork', 'name' => $t['source'][0], 'url' => $t['source'][1]] : null,
                ]))->values()->all(),
            ]);

        // The core governance terms, answered on the page as questions (the layout prints
        // them visibly and marks them up as FAQPage): the same answers as their own pages.
        $seo->withFaq(collect(GlossaryTerms::all())->filter(fn ($t) => $t['faq'] && $t['answer'])
            ->map(fn ($t) => ['question' => $t['question'], 'answer' => $t['answer']])->values()->all());

        return view('site.pages.glossary', ['seo' => $seo, 'groups' => $groups, 'count' => $all->count()]);
    }

    public static function anchor(string $taxonomy, string $slug): string
    {
        return str_replace('_', '-', $taxonomy === 'actor' ? $slug : $taxonomy.'-'.$slug);
    }

    /** @return list<array{id:string, term:string, definition:string, source:?array, see:list<array{0:string,1:string}>, page:?string, group:string}> */
    private function curated(): array
    {
        // The list shows each term's short answer where one is written; its page has the rest.
        return collect(GlossaryTerms::all())->map(fn ($t) => [
            'id' => $t['id'], 'term' => $t['term'], 'definition' => GlossaryTerms::lead($t), 'source' => $t['source'], 'see' => $t['see'],
            'page' => $t['canonical'] ?? route('glossary.show', $t['id']), 'group' => $t['group'],
        ])->values()->all();
    }

    /**
     * /glossary/{term}: one curated term on its own page, answering "what is X":
     * the definition first, where it comes from, the laws and duties on record
     * that use it, and the terms it is bound up with.
     */
    public function show(string $term): View|RedirectResponse
    {
        $t = GlossaryTerms::find($term);
        abort_unless($t, 404);
        // A term whose subject has its own record page here is answered there.
        if ($t['canonical']) {
            return redirect()->to($t['canonical'], 301);
        }
        $policies = GlossaryTerms::policiesUsing($t);
        $obligations = GlossaryTerms::obligationsUsing($t);
        $related = GlossaryTerms::related($t);
        $url = GlossaryTerms::url($t['id']);
        $setId = route('glossary').'#terms';

        $faq = [['question' => $t['answer'] ? $t['question'] : 'What does "'.$t['short'].'" mean?', 'answer' => GlossaryTerms::lead($t)]];
        if ($t['source']) {
            $faq[] = ['question' => 'Where does the term '.$t['inline'].' come from?', 'answer' => 'This explanation follows '.$t['source'][0].'. It is a plain-language paraphrase for orientation; the source has the binding wording.'];
        }
        if ($policies->isNotEmpty()) {
            $faq[] = ['question' => 'Which laws and policies use the term '.$t['inline'].'?', 'answer' => 'Among the records on this site: '.$policies->take(5)->map(fn ($p) => ($p->short_title ?: $p->title).' ('.($p->jurisdiction?->short_name ?: $p->jurisdiction?->name).')')->join('; ', ' and ').'.'];
        }

        $seo = Seo::make(
            PageTitle::fit($t['short'], [': Definition & Where It Applies', ': Definition and Meaning', ': Definition', '']),
            PageTitle::description($t['answer'] ?? $t['short'].': '.$t['definition']),
            $url,
        )->withBreadcrumbs([['Home', route('home')], ['Glossary', route('glossary')], [$t['short'], $url]])
            ->withCard('glossary', $t['id'], md5($t['term'].($t['source'][0] ?? '')))
            ->withPageType('WebPage', ['name' => $t['term'], 'mainEntity' => ['@id' => $url.'#term']])
            ->withJsonLd(array_filter([
                '@type' => 'DefinedTerm',
                '@id' => $url.'#term',
                'name' => $t['term'],
                'termCode' => $t['id'],
                'description' => GlossaryTerms::lead($t),
                'url' => $url,
                'inDefinedTermSet' => ['@type' => 'DefinedTermSet', '@id' => $setId, 'name' => 'AI governance glossary', 'url' => route('glossary')],
                'subjectOf' => $t['source'] ? ['@type' => 'CreativeWork', 'name' => $t['source'][0], 'url' => $t['source'][1]] : null,
            ]))
            ->withFaq($faq);

        return view('site.pages.glossary-term', compact('seo', 't', 'policies', 'obligations', 'related'));
    }
}
