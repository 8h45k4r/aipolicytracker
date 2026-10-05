<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\TaxonomyTerm;
use App\Services\Glossary\GlossaryTerms;
use App\Support\PageTitle;
use App\Support\Seo;
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

    public function index(): View
    {
        $groups = [['heading' => 'Core terms', 'id' => 'core', 'terms' => $this->curated()]];
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
            'Plain-language definitions of AI governance terms: AI system, provider, deployer, high-risk, general-purpose AI, conformity assessment, FRIA, human oversight, AI literacy and more, each linked to its source.',
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

        return view('site.pages.glossary', ['seo' => $seo, 'groups' => $groups, 'count' => $all->count()]);
    }

    public static function anchor(string $taxonomy, string $slug): string
    {
        return str_replace('_', '-', $taxonomy === 'actor' ? $slug : $taxonomy.'-'.$slug);
    }

    /** @return list<array{id:string, term:string, definition:string, source:?array, see:list<array{0:string,1:string}>, page:?string}> */
    private function curated(): array
    {
        return collect(GlossaryTerms::all())->map(fn ($t) => [
            'id' => $t['id'], 'term' => $t['term'], 'definition' => $t['definition'], 'source' => $t['source'], 'see' => $t['see'], 'page' => GlossaryTerms::url($t['id']),
        ])->values()->all();
    }

    /**
     * /glossary/{term}: one curated term on its own page, answering "what is X":
     * the definition first, where it comes from, the laws and duties on record
     * that use it, and the terms it is bound up with.
     */
    public function show(string $term): View
    {
        $t = GlossaryTerms::find($term);
        abort_unless($t, 404);
        $policies = GlossaryTerms::policiesUsing($t);
        $obligations = GlossaryTerms::obligationsUsing($t);
        $related = GlossaryTerms::related($t);
        $url = GlossaryTerms::url($t['id']);
        $setId = route('glossary').'#terms';

        $faq = [['question' => 'What does "'.$t['short'].'" mean?', 'answer' => $t['definition']]];
        if ($t['source']) {
            $faq[] = ['question' => 'Where does the term '.$t['short'].' come from?', 'answer' => 'This explanation follows '.$t['source'][0].'. It is a plain-language paraphrase for orientation; the source has the binding wording.'];
        }
        if ($policies->isNotEmpty()) {
            $faq[] = ['question' => 'Which laws and policies use the term '.$t['short'].'?', 'answer' => 'Among the records on this site: '.$policies->take(5)->map(fn ($p) => ($p->short_title ?: $p->title).' ('.($p->jurisdiction?->short_name ?: $p->jurisdiction?->name).')')->join('; ', ' and ').'.'];
        }

        $seo = Seo::make(
            PageTitle::fit($t['short'], [': Definition & Where It Applies', ': Definition and Meaning', ': Definition', '']),
            PageTitle::description($t['short'].': '.$t['definition']),
            $url,
        )->withBreadcrumbs([['Home', route('home')], ['Glossary', route('glossary')], [$t['short'], $url]])
            ->withPageType('WebPage', ['name' => $t['term'], 'mainEntity' => ['@id' => $url.'#term']])
            ->withJsonLd(array_filter([
                '@type' => 'DefinedTerm',
                '@id' => $url.'#term',
                'name' => $t['term'],
                'termCode' => $t['id'],
                'description' => $t['definition'],
                'url' => $url,
                'inDefinedTermSet' => ['@type' => 'DefinedTermSet', '@id' => $setId, 'name' => 'AI governance glossary', 'url' => route('glossary')],
                'subjectOf' => $t['source'] ? ['@type' => 'CreativeWork', 'name' => $t['source'][0], 'url' => $t['source'][1]] : null,
            ]))
            ->withFaq($faq);

        return view('site.pages.glossary-term', compact('seo', 't', 'policies', 'obligations', 'related'));
    }
}
