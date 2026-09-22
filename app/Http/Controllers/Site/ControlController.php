<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Control;
use App\Models\TaxonomyTerm;
use App\Services\PolicyData\ControlIntelligence;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Controls: what an organisation operates to meet legal duties. The index answers
 * "which controls exist and how much law does each one reach"; a control page
 * answers "one control, many requirements": every duty it serves, in every
 * jurisdiction, the evidence it produces and the standards clauses it cites.
 */
class ControlController extends Controller
{
    public function __construct(private readonly ControlIntelligence $intelligence) {}

    public function index(Request $request): View
    {
        $kind = array_key_exists((string) $request->query('kind'), Control::KINDS) ? $request->query('kind') : null;
        $q = trim((string) $request->query('q', ''));

        $controls = Control::published()->with(['evidence', 'frameworkReferences', 'obligations.policyInstrument.jurisdiction'])
            ->when($kind, fn ($query) => $query->where('kind', $kind))
            ->search($q)
            ->orderBy('title')->get()
            ->filter(fn ($c) => $c->obligations->contains(fn ($o) => $o->published_at !== null));

        $totals = [
            'controls' => $controls->count(),
            'obligations' => $controls->flatMap(fn ($c) => $c->obligations->pluck('id'))->unique()->count(),
            'jurisdictions' => $controls->flatMap(fn ($c) => $c->obligations->pluck('policyInstrument.jurisdiction_id'))->filter()->unique()->count(),
            'evidence_types' => $controls->flatMap(fn ($c) => $c->evidence->pluck('evidence_type'))->unique()->count(),
        ];
        $byKind = $controls->groupBy('kind')->map->count();

        $title = $kind ? Control::KINDS[$kind].' controls for AI governance' : 'AI governance controls: one control, many legal duties';
        $seo = Seo::make(
            $title,
            'The controls an organisation operates to meet AI legal duties, each with the duties it satisfies across jurisdictions, the evidence it produces, the risks it addresses and the ISO/IEC 42001 and NIST AI RMF clauses it corresponds to.',
            route('controls.index', $kind ? ['kind' => $kind] : []),
            $q === ''
        )->withBreadcrumbs([['Home', route('home')], ['Controls', route('controls.index')]])
            ->withPageType('CollectionPage', [
                'name' => $title,
                'mainEntity' => Seo::itemList($controls, fn ($c) => $c->title, fn ($c) => $c->url(), 'AI governance controls'),
            ])
            ->withJsonLd(Seo::dataset(
                'AI governance control catalogue',
                'Original catalogue of organisational controls for AI governance, each linked to the legal duties it satisfies or supports, the evidence it produces and the standards clauses it corresponds to.',
                route('controls.index'),
                ['text/csv' => route('open-data.csv', 'controls'), 'application/x-ndjson' => route('open-data.ndjson', 'controls')],
                $controls->max('updated_at'),
            ));

        return view('site.controls.index', compact('seo', 'controls', 'totals', 'byKind', 'kind', 'q'));
    }

    public function show(Control $control): View
    {
        abort_unless($control->published_at, 404);
        $control->load(['evidence', 'frameworkReferences', 'obligations.policyInstrument.jurisdiction']);
        $duties = $control->obligations->filter(fn ($o) => $o->published_at && $o->policyInstrument?->published_at)
            ->sortBy([fn ($a, $b) => strcmp($a->pivot->relationship, $b->pivot->relationship), fn ($a, $b) => strcmp($a->policyInstrument->jurisdiction->name, $b->policyInstrument->jurisdiction->name)])
            ->values();
        $byJurisdiction = $duties->groupBy(fn ($o) => $o->policyInstrument->jurisdiction->name);
        $risks = $this->intelligence->risksFor($control);
        $related = Control::published()->whereIn('slug', $control->related_controls ?? [])->orderBy('title')->get();
        $evidenceTypes = TaxonomyTerm::where('taxonomy', 'evidence_type')->get()->keyBy('slug');

        $seo = Seo::make(
            Seo::fitTitle($control->title, [': the AI duties it satisfies and the evidence it needs', ': duties, evidence and clauses', '']),
            Str::limit(trim(preg_replace('/\s+/', ' ', $control->purpose)).' Serves '.$duties->count().' recorded '.Str::plural('duty', $duties->count()).' across '.$byJurisdiction->count().' '.Str::plural('jurisdiction', $byJurisdiction->count()).'.', 158),
            $control->url(),
            $control->isIndexable()
        )->withBreadcrumbs([['Home', route('home')], ['Controls', route('controls.index')], [$control->title, $control->url()]])
            ->withModified($control->updated_at)
            ->withPublished($control->created_at)
            ->withAlternate('text/markdown', route('controls.context', $control->slug))
            ->withPageProperties([
                'name' => $control->title,
                'about' => ['@type' => 'DefinedTerm', 'name' => $control->kindLabel(), 'inDefinedTermSet' => route('controls.index')],
                'mentions' => $duties->map(fn ($o) => ['@type' => $o->policyInstrument->is_binding ? 'Legislation' : 'CreativeWork', 'name' => $o->policyInstrument->short_title ?: $o->policyInstrument->title, 'url' => $o->policyInstrument->url()])->unique('url')->values()->all(),
            ] + Seo::provenance($control))
            ->withJsonLd(Seo::dataset(
                $control->title,
                Str::limit(preg_replace('/\s+/', ' ', $control->purpose), 300),
                $control->url(),
                ['text/markdown' => route('controls.context', $control->slug)],
                $control->updated_at,
                null,
                ['sameAs' => config('aipolicytracker.github_url').'/blob/main/data/controls/'.$control->slug.'.yaml'],
            ))
            ->withFaq(array_values(array_filter([
                ['question' => 'Which legal duties does "'.$control->title.'" satisfy?', 'answer' => $duties->where('pivot.relationship', 'satisfies')->isNotEmpty()
                    ? 'It is recorded as satisfying '.$duties->where('pivot.relationship', 'satisfies')->count().' and supporting '.$duties->where('pivot.relationship', 'supports')->count().' duties across '.$byJurisdiction->keys()->join(', ', ' and ').'. A mapping means the control, operated properly, does the work the duty asks for; the official text decides whether it is enough.'
                    : 'It supports '.$duties->count().' recorded duties without discharging any on its own; each still needs the duty-specific evidence its page lists.'],
                ['question' => 'What evidence shows this control is operating?', 'answer' => $control->evidence->map(fn ($e) => $e->title)->join(', ', ' and ').'. Owner: '.$control->owner_role.'. Frequency: '.strtolower($control->frequencyLabel()).'.'],
            ])));

        return view('site.controls.show', compact('seo', 'control', 'duties', 'byJurisdiction', 'risks', 'related', 'evidenceTypes'));
    }
}
