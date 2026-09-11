<?php

namespace App\Services\PolicyData;

use App\Models\Jurisdiction;
use App\Models\Obligation;
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
}
