<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Jurisdiction;
use App\Services\Hubs\HubCatalog;
use App\Services\PolicyData\ComparisonBuilder;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompareController extends Controller
{
    public function index(Request $request, ComparisonBuilder $builder): View
    {
        // The count tells a reader whether comparing a jurisdiction will show anything.
        $all = Jurisdiction::published()->withCount(['policyInstruments' => fn ($q) => $q->published()])->orderBy('name')->get();
        $raw = $request->query('j', '');
        $selected = collect(is_array($raw) ? $raw : explode(',', (string) $raw))->map(fn ($v) => trim((string) $v))->filter()->unique()->take(4)->values();
        $jurisdictions = $selected->isEmpty() ? collect() : $all->whereIn('slug', $selected)->values();
        $rows = $jurisdictions->count() >= 2 ? $builder->build($jurisdictions) : [];
        $curated = collect(config('content.comparisons'))->map(fn ($c, $slug) => ['slug' => $slug] + $c)->values();
        $names = Jurisdiction::published()->pluck('name', 'slug');
        $pairs = collect(HubCatalog::indexablePairs())->map(fn ($p) => ['slug' => $p, 'a' => $names[HubCatalog::parsePair($p)[0]] ?? null, 'b' => $names[HubCatalog::parsePair($p)[1]] ?? null])->filter(fn ($p) => $p['a'] && $p['b'])->values();

        $seo = Seo::make(
            'Compare AI regulation across jurisdictions',
            'Compare 2 to 4 jurisdictions side by side: regulatory status, binding legislation, high-risk and generative-AI rules, transparency, impact assessment, data governance, oversight, public-sector rules, dates and official sources.',
            route('compare.index'),
            $jurisdictions->isEmpty()
        )->withBreadcrumbs([['Home', route('home')], ['Compare', route('compare.index')]])
            ->withPageType('CollectionPage', [
                'mainEntity' => Seo::itemList(
                    $curated,
                    fn ($c) => is_array($c) ? ($c['title'] ?? $c['short'] ?? '') : (string) $c,
                    fn ($c, $k = null) => is_array($c) && isset($c['slug']) ? route('compare.show', $c['slug']) : null,
                    'Prepared jurisdiction comparisons',
                ),
            ]);

        return view('site.compare.index', compact('seo', 'all', 'jurisdictions', 'rows', 'curated', 'selected', 'pairs'));
    }

    public function show(string $comparison, ComparisonBuilder $builder): View|RedirectResponse
    {
        abort_unless(preg_match('/^[a-z0-9-]{1,120}$/', $comparison) === 1, 404);
        $config = config('content.comparisons.'.$comparison);
        if (! $config) {
            return $this->pair($comparison, $builder);
        }
        $jurisdictions = Jurisdiction::published()->whereIn('slug', $config['jurisdictions'])->get()->sortBy(fn ($j) => array_search($j->slug, $config['jurisdictions'], true))->values();
        abort_if($jurisdictions->count() < 2, 404);
        $rows = $builder->build($jurisdictions);
        $lastModified = $jurisdictions->max('updated_at');
        [$a, $b] = [$jurisdictions[0], $jurisdictions[1]];
        $overlap = $builder->overlap($a, $b);
        $left = $builder->whatsLeft($a, $b);

        $seo = Seo::make($config['title'], $config['description'], route('compare.show', $comparison))
            ->withBreadcrumbs([['Home', route('home')], ['Compare', route('compare.index')], [$config['short'], route('compare.show', $comparison)]])
            ->withModified($lastModified)
            ->withPageType('CollectionPage', ['name' => $config['title']]);
        if (! empty($config['faq'])) {
            $seo->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => collect($config['faq'])->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]])->values()->all()]);
        }
        $faq = $config['faq'] ?? [];

        return view('site.compare.show', compact('seo', 'config', 'jurisdictions', 'rows', 'comparison', 'overlap', 'left', 'a', 'b', 'faq'));
    }

    /**
     * /compare/<a>-vs-<b> for any two published jurisdictions. One canonical
     * order per pair (alphabetical by slug); the other order redirects, and a
     * pair a curated comparison covers redirects to that page. Only the pairs
     * in config/hubs.php are offered to search engines.
     */
    private function pair(string $slug, ComparisonBuilder $builder): View|RedirectResponse
    {
        $pair = HubCatalog::parsePair($slug);
        abort_unless($pair, 404);
        [$x, $y] = $pair;
        $jurisdictions = Jurisdiction::published()->whereIn('slug', [$x, $y])->get()->sortBy(fn ($j) => $j->slug === $x ? 0 : 1)->values();
        abort_if($jurisdictions->count() < 2, 404);
        if ($curated = HubCatalog::curatedFor($x, $y)) {
            return redirect()->to(route('compare.show', $curated), 301);
        }
        $canonical = HubCatalog::pairSlug($x, $y);
        if ($canonical !== $slug) {
            return redirect()->to(route('compare.show', $canonical), 301);
        }
        [$a, $b] = [$jurisdictions[0], $jurisdictions[1]];
        $rows = $builder->build($jurisdictions);
        $overlap = $builder->overlap($a, $b);
        $left = $builder->whatsLeft($a, $b);
        $counts = fn (Jurisdiction $j) => ['instruments' => $j->policyInstruments()->published()->count(), 'binding' => $j->policyInstruments()->published()->where('is_binding', true)->count()];
        $ca = $counts($a);
        $cb = $counts($b);
        $shared = count(array_filter($overlap, fn ($r) => $r['both']));
        $indexable = HubCatalog::isPairIndexable($canonical) && $a->isPageIndexable() && $b->isPageIndexable();
        $lastModified = $jurisdictions->max('updated_at');

        $intro = sprintf(
            '%s records %d AI policy %s (%d binding); %s records %d (%d binding). They share %d obligation %s, and %d of %s\'s binding duties fall in categories where %s has none. Every cell below is computed from published records linked to their official sources.',
            $a->nameWithArticleCapitalised(), $ca['instruments'], Str::plural('instrument', $ca['instruments']), $ca['binding'],
            $b->nameWithArticle(), $cb['instruments'], $cb['binding'], $shared, Str::plural('category', $shared),
            $left['new']->count(), $b->nameWithArticle(), $a->nameWithArticle()
        );
        $faq = [
            ['question' => 'Which has stricter AI rules, '.$a->nameWithArticle().' or '.$b->nameWithArticle().'?', 'answer' => ($ca['binding'] === 0 && $cb['binding'] === 0) ? 'Neither records an AI-specific binding instrument; both govern AI through strategies, guidance and existing law. The side-by-side table shows what each relies on.' : sprintf('%s records %d binding AI %s and %s records %d. Strictness also depends on scope and enforcement, which the table sets out row by row with links to each record.', $a->nameWithArticleCapitalised(), $ca['binding'], Str::plural('instrument', $ca['binding']), $b->nameWithArticle(), $cb['binding'])],
            ['question' => 'If we comply with '.$a->nameWithArticle().', what is left for '.$b->nameWithArticle().'?', 'answer' => $left['new']->isEmpty() ? sprintf('No binding duty recorded for %s falls outside the categories %s already binds; the remaining work is checking %d shared duties against their own provisions.', $b->nameWithArticle(), $a->nameWithArticle(), $left['shared']->count()) : sprintf('%d binding %s in categories %s does not bind (%s), plus %d shared duties to check against their own provisions.', $left['new']->count(), Str::plural('duty', $left['new']->count()), $a->nameWithArticle(), $left['new']->pluck('category')->unique()->map(fn ($c) => str_replace('_', ' ', $c))->take(4)->join(', '), $left['shared']->count())],
            ['question' => 'Where does this comparison come from?', 'answer' => 'From the published records for both jurisdictions: instruments, obligations, deadlines and official sources. Nothing on this page is written by hand; when a record changes, the comparison changes with it.'],
        ];

        $seo = Seo::make(PageTitle::comparePair($a, $b), PageTitle::description($intro), route('compare.show', $canonical), $indexable)
            ->withBreadcrumbs([['Home', route('home')], ['Compare', route('compare.index')], [($a->short_name ?: $a->name).' vs '.($b->short_name ?: $b->name), route('compare.show', $canonical)]])
            ->withModified($lastModified)
            ->withPageType('CollectionPage', ['name' => PageTitle::comparePair($a, $b), 'description' => $intro])
            ->withFaq($faq);
        $config = ['title' => ($a->short_name ?: $a->name).' vs '.($b->short_name ?: $b->name).': AI regulation compared', 'intro' => $intro, 'short' => ($a->short_name ?: $a->name).' vs '.($b->short_name ?: $b->name)];
        $comparison = $canonical;

        return view('site.compare.show', compact('seo', 'config', 'jurisdictions', 'rows', 'comparison', 'overlap', 'left', 'a', 'b', 'faq'));
    }
}
