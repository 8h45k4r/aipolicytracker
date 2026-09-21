<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Jurisdiction;
use App\Services\PolicyData\FrameworkCrosswalk;
use App\Support\Seo;
use Illuminate\View\View;

/**
 * Browseable crosswalks between legal duties and the standards organisations are audited
 * against. Pages state how many duties are mapped against how many exist, so a reader can
 * see the coverage rather than assume it.
 */
class FrameworkController extends Controller
{
    public function __construct(private readonly FrameworkCrosswalk $crosswalk) {}

    public function index(): View
    {
        $frameworks = $this->crosswalk->summary();
        $covered = $frameworks->where('obligations', '>', 0);
        $crosswalkRows = $covered->mapWithKeys(fn (array $f) => [$f['key'] => $this->crosswalk->jurisdictionsFor($f['key'])]);

        $seo = Seo::make(
            'AI framework crosswalks: which legal duties map to ISO/IEC 42001 and the NIST AI RMF',
            'Browse the recorded crosswalks between AI laws and the standards organisations are audited against. Each row cites the clause or function a duty corresponds to, with the jurisdiction, instrument and confidence of the mapping.',
            route('frameworks.index'),
        )->withBreadcrumbs([['Home', route('home')], ['Frameworks', route('frameworks.index')]])
            ->withPageType('CollectionPage', [
                'mainEntity' => Seo::itemList(
                    $covered->all(),
                    fn ($f) => $f['name'],
                    fn ($f) => route('frameworks.show', $f['slug']),
                    'Frameworks crosswalked to legal duties',
                ),
            ]);

        return view('site.frameworks.index', compact('seo', 'frameworks', 'covered', 'crosswalkRows'));
    }

    public function show(string $framework): View
    {
        $key = $this->crosswalk->keyForSlug($framework);
        abort_unless($key, 404);
        $data = $this->crosswalk->framework($key);
        abort_if($data['mappings'] === 0, 404);
        $meta = $data['meta'];

        $seo = Seo::make(
            $meta['name'].': which AI legal duties map to it',
            sprintf('%d recorded crosswalks from AI legal duties in %d jurisdictions to %s, grouped by %s. Each row cites a clause number only and links the duty it came from.',
                $data['mappings'], $data['jurisdictions']->count(), $meta['short'], strtolower($meta['unit'])),
            route('frameworks.show', $meta['slug']),
            $data['indexable'],
        )->withBreadcrumbs([['Home', route('home')], ['Frameworks', route('frameworks.index')], [$meta['short'], route('frameworks.show', $meta['slug'])]])
            ->withPageType('CollectionPage', ['name' => $meta['name'].' crosswalk'])
            ->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => [
                ['@type' => 'Question', 'name' => 'What is '.$meta['name'].'?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $meta['summary']]],
                ['@type' => 'Question', 'name' => 'Does '.$meta['short'].' make an organisation legally compliant?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No. '.($meta['certifiable'] ? 'Certification' : 'Adoption').' evidences a management practice, not compliance with any statute. A crosswalk shows where the two overlap so existing evidence can be reused; it does not transfer legal obligations.']],
            ]]);

        return view('site.frameworks.show', compact('seo', 'data', 'meta'));
    }

    public function crosswalk(string $framework, string $jurisdiction): View
    {
        $key = $this->crosswalk->keyForSlug($framework);
        abort_unless($key, 404);
        $j = Jurisdiction::published()->where('slug', $jurisdiction)->first();
        abort_unless($j, 404);
        $data = $this->crosswalk->crosswalk($key, $j);
        abort_if($data['rows']->isEmpty(), 404);
        $meta = $data['meta'];
        $title = $j->name.' AI rules mapped to '.$meta['short'];

        $seo = Seo::make(
            $title,
            sprintf('%d of the %d recorded %s AI duties are crosswalked to %s, citing the clause each one corresponds to. Use it to see which duties existing %s evidence already reaches.',
                $data['rows']->count(), $data['total_obligations'], $j->name, $meta['name'], $meta['short']),
            route('frameworks.crosswalk', [$meta['slug'], $j->slug]),
            $data['indexable'],
        )->withBreadcrumbs([['Home', route('home')], ['Frameworks', route('frameworks.index')], [$meta['short'], route('frameworks.show', $meta['slug'])], [$j->name, route('frameworks.crosswalk', [$meta['slug'], $j->slug])]])
            ->withPageType('CollectionPage', ['name' => $title])
            ->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => [
                ['@type' => 'Question', 'name' => 'Does '.$meta['short'].' '.($meta['certifiable'] ? 'certification ' : 'adoption ').'satisfy '.$j->name.' AI rules?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No. '.$meta['short'].' is '.($meta['certifiable'] ? 'a certifiable management system standard' : 'a voluntary framework').' and carries no legal force in '.$j->name.'. This crosswalk records that '.$data['rows']->count().' of the '.$data['total_obligations'].' duties tracked here have a corresponding clause, which means the evidence may be reusable, not that the duty is discharged.']],
                ['@type' => 'Question', 'name' => 'How many '.$j->name.' AI duties map to '.$meta['short'].'?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $data['rows']->count().' of '.$data['total_obligations'].' duties recorded for '.$j->name.' carry a mapping to '.$meta['name'].'.']],
            ]]);

        return view('site.frameworks.crosswalk', compact('seo', 'data', 'meta', 'j'));
    }
}
