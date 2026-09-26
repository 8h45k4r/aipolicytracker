<?php

namespace App\Services\PolicyData;

use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\TaxonomyTerm;
use Illuminate\Support\Collection;

/**
 * Builds the row/column matrix for jurisdiction comparisons from verified data.
 * Every cell is derived from published records; nothing is hand-written here.
 */
class ComparisonBuilder
{
    public const CATEGORIES = [
        'status' => 'Regulatory status',
        'binding' => 'Binding AI legislation',
        'high_risk' => 'High-risk AI rules',
        'genai' => 'Generative and general-purpose AI rules',
        'transparency' => 'Transparency obligations',
        'impact_assessment' => 'Impact assessment',
        'data_governance' => 'Data governance and personal data',
        'human_oversight' => 'Human oversight',
        'public_sector' => 'Public-sector requirements',
        'dates' => 'Key effective dates',
        'sources' => 'Official sources',
    ];

    /** @param Collection<int, Jurisdiction> $jurisdictions */
    public function build(Collection $jurisdictions): array
    {
        $rows = [];
        foreach (self::CATEGORIES as $key => $label) {
            $rows[$key] = ['label' => $label, 'cells' => []];
        }
        foreach ($jurisdictions as $j) {
            $policies = $j->policyInstruments()->published()->with(['terms', 'deadlines', 'procurementRules'])->orderByDesc('is_binding')->get();
            $ids = $policies->pluck('id');
            $obligations = Obligation::published()->whereIn('policy_instrument_id', $ids)->with(['terms', 'policyInstrument'])->get();

            $rows['status']['cells'][$j->slug] = ['text' => $j->regulatory_status_summary, 'links' => []];
            $binding = $policies->where('is_binding', true);
            $rows['binding']['cells'][$j->slug] = $binding->isEmpty()
                ? ['text' => 'No AI-specific binding legislation recorded. See status and guidance.', 'links' => []]
                : ['text' => null, 'links' => $binding->map(fn ($p) => ['name' => ($p->short_title ?: $p->title).' ('.$p->statusEnum()->label().')', 'url' => $p->url()])->values()->all()];
            $rows['high_risk']['cells'][$j->slug] = $this->obligationCell($obligations->filter(fn ($o) => $o->terms->contains(fn ($t) => $t->taxonomy === 'risk_category' && $t->slug === 'high_risk') || $o->policyInstrument->terms->contains(fn ($t) => $t->taxonomy === 'risk_category' && $t->slug === 'high_risk')), 'No high-risk tiering recorded.');
            $rows['genai']['cells'][$j->slug] = $this->obligationCell($obligations->filter(fn ($o) => $o->terms->contains(fn ($t) => $t->taxonomy === 'use_case' && $t->slug === 'generative_ai')), 'No generative-AI-specific obligations recorded.');
            $rows['transparency']['cells'][$j->slug] = $this->obligationCell($obligations->where('category', 'transparency'), 'No transparency obligations recorded.');
            $rows['impact_assessment']['cells'][$j->slug] = $this->obligationCell($obligations->where('category', 'impact_assessment'), 'No impact-assessment obligations recorded.');
            $rows['data_governance']['cells'][$j->slug] = $this->obligationCell($obligations->whereIn('category', ['data_governance', 'privacy_data_protection']), 'No data-governance obligations recorded.');
            $rows['human_oversight']['cells'][$j->slug] = $this->obligationCell($obligations->where('category', 'human_oversight'), 'No human-oversight obligations recorded.');
            $procurement = $policies->flatMap->procurementRules;
            $publicSector = $obligations->where('category', 'public_sector_use');
            $rows['public_sector']['cells'][$j->slug] = ($procurement->isEmpty() && $publicSector->isEmpty())
                ? ['text' => 'No public-sector-specific requirements recorded.', 'links' => []]
                : ['text' => $procurement->pluck('title')->implode('; '), 'links' => $publicSector->map(fn ($o) => ['name' => $o->title, 'url' => $o->url()])->values()->all()];
            $dates = $policies->flatMap(fn ($p) => $p->deadlines->whereIn('deadline_status', ['scheduled', 'passed'])->whereNotNull('due_on')->map(fn ($d) => ['date' => $d->due_on, 'text' => $d->displayDate().': '.$d->title.' ('.($p->short_title ?: $p->title).')', 'url' => $p->url()]))->sortBy('date')->values();
            $rows['dates']['cells'][$j->slug] = $dates->isEmpty() ? ['text' => 'No dated milestones recorded.', 'links' => []] : ['text' => null, 'links' => $dates->map(fn ($d) => ['name' => $d['text'], 'url' => $d['url']])->all()];
            $rows['sources']['cells'][$j->slug] = ['text' => null, 'links' => collect($j->official_sources ?? [])->map(fn ($s) => ['name' => $s['title'], 'url' => $s['url'], 'external' => true])->all()];
        }

        return $rows;
    }

    private function obligationCell(Collection $obligations, string $empty): array
    {
        if ($obligations->isEmpty()) {
            return ['text' => $empty, 'links' => []];
        }
        $binding = $obligations->where('is_binding', true)->count();

        return [
            'text' => $binding.' binding, '.($obligations->count() - $binding).' voluntary',
            'links' => $obligations->sortByDesc('is_binding')->take(6)->map(fn ($o) => ['name' => $o->title.($o->is_binding ? '' : ' (voluntary)'), 'url' => $o->url()])->values()->all(),
        ];
    }

    /**
     * The obligation overlap between two jurisdictions, by category: how many
     * binding and voluntary duties each records, and which side has what.
     *
     * @return list<array{category:string, name:string, a:array{binding:int,voluntary:int}, b:array{binding:int,voluntary:int}, both:bool}>
     */
    public function overlap(Jurisdiction $a, Jurisdiction $b): array
    {
        $names = TaxonomyTerm::where('taxonomy', 'obligation_category')->pluck('name', 'slug')->all();
        $count = fn (Jurisdiction $j) => Obligation::published()
            ->whereHas('policyInstrument', fn ($p) => $p->published()->where('jurisdiction_id', $j->id))
            ->selectRaw('category, is_binding, COUNT(*) as n')->groupBy('category', 'is_binding')->get()
            ->groupBy('category')->map(fn ($rows) => ['binding' => (int) $rows->where('is_binding', true)->sum('n'), 'voluntary' => (int) $rows->where('is_binding', false)->sum('n')]);
        $ca = $count($a);
        $cb = $count($b);
        $rows = [];
        foreach ($ca->keys()->merge($cb->keys())->unique() as $category) {
            $x = $ca[$category] ?? ['binding' => 0, 'voluntary' => 0];
            $y = $cb[$category] ?? ['binding' => 0, 'voluntary' => 0];
            $rows[] = ['category' => $category, 'name' => $names[$category] ?? ucfirst(str_replace('_', ' ', $category)), 'a' => $x, 'b' => $y, 'both' => ($x['binding'] + $x['voluntary']) > 0 && ($y['binding'] + $y['voluntary']) > 0];
        }
        usort($rows, fn ($p, $q) => [$q['both'], $q['a']['binding'] + $q['b']['binding'], $p['name']] <=> [$p['both'], $p['a']['binding'] + $p['b']['binding'], $q['name']]);

        return $rows;
    }

    /**
     * "If you comply with A, what is left for B": B's binding duties in
     * categories where A records no binding duty, then B's binding duties in
     * shared categories, which need checking against A's provisions rather
     * than redoing from scratch.
     *
     * @return array{new:Collection<int,Obligation>, shared:Collection<int,Obligation>}
     */
    public function whatsLeft(Jurisdiction $a, Jurisdiction $b): array
    {
        $aBinding = Obligation::published()->where('is_binding', true)
            ->whereHas('policyInstrument', fn ($p) => $p->published()->where('jurisdiction_id', $a->id))
            ->pluck('category')->unique()->all();
        $bBinding = Obligation::published()->where('is_binding', true)->with('policyInstrument')
            ->whereHas('policyInstrument', fn ($p) => $p->published()->where('jurisdiction_id', $b->id))
            ->orderBy('category')->orderBy('title')->get();

        return [
            'new' => $bBinding->filter(fn ($o) => ! in_array($o->category, $aBinding, true))->values(),
            'shared' => $bBinding->filter(fn ($o) => in_array($o->category, $aBinding, true))->values(),
        ];
    }
}
