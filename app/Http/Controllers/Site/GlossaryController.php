<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\TaxonomyTerm;
use App\Support\Seo;
use Illuminate\Support\Facades\Route;
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
                    'url' => route('glossary').'#'.$t['id'],
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

    /** @return list<array{id:string, term:string, definition:string, source:?array, see:list<array{0:string,1:string}>}> */
    private function curated(): array
    {
        $out = [];
        foreach (config('glossary.terms', []) as $id => $t) {
            $see = [];
            foreach ($t['see'] ?? [] as [$label, $name, $params]) {
                // A link to a record that is not published (or a route that does not exist)
                // is left out rather than shipped as a 404.
                if (Route::has($name)) {
                    $see[] = [$label, route($name, $params)];
                }
            }
            $out[] = ['id' => $id, 'term' => $t['term'], 'definition' => $t['definition'], 'source' => $t['source'] ?? null, 'see' => $see];
        }

        return $out;
    }
}
