<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Jurisdiction;
use App\Services\PolicyData\ComparisonBuilder;
use App\Support\Seo;
use Illuminate\Http\Request;
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

        $seo = Seo::make(
            'Compare AI regulation across jurisdictions',
            'Compare 2 to 4 jurisdictions side by side: regulatory status, binding legislation, high-risk and generative-AI rules, transparency, impact assessment, data governance, oversight, public-sector rules, dates and official sources.',
            route('compare.index'),
            $jurisdictions->isEmpty()
        )->withBreadcrumbs([['Home', route('home')], ['Compare', route('compare.index')]]);

        return view('site.compare.index', compact('seo', 'all', 'jurisdictions', 'rows', 'curated', 'selected'));
    }

    public function show(string $comparison, ComparisonBuilder $builder): View
    {
        $config = config('content.comparisons.'.$comparison);
        abort_unless($config, 404);
        $jurisdictions = Jurisdiction::published()->whereIn('slug', $config['jurisdictions'])->get()->sortBy(fn ($j) => array_search($j->slug, $config['jurisdictions'], true))->values();
        abort_if($jurisdictions->count() < 2, 404);
        $rows = $builder->build($jurisdictions);
        $lastModified = $jurisdictions->max('updated_at');

        $seo = Seo::make($config['title'], $config['description'], route('compare.show', $comparison))
            ->withBreadcrumbs([['Home', route('home')], ['Compare', route('compare.index')], [$config['short'], route('compare.show', $comparison)]])
            ->withModified($lastModified)
            ->withJsonLd(['@type' => 'WebPage', 'name' => $config['title'], 'url' => route('compare.show', $comparison), 'dateModified' => $lastModified?->toIso8601String(), 'isPartOf' => ['@id' => url('/').'#website']]);
        if (! empty($config['faq'])) {
            $seo->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => collect($config['faq'])->map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]])->values()->all()]);
        }

        return view('site.compare.show', compact('seo', 'config', 'jurisdictions', 'rows', 'comparison'));
    }
}
