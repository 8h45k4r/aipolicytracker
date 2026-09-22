<?php

namespace App\Services\PolicyData;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;

/**
 * Public JSON representation of records, shared by the read-only API, the
 * per-page JSON downloads, and the open-data export bundle.
 */
class PolicySerializer
{
    public function jurisdiction(Jurisdiction $j, bool $withPolicies = false): array
    {
        $out = [
            'slug' => $j->slug,
            'name' => $j->name,
            'short_name' => $j->short_name,
            'iso_code' => $j->iso_code,
            'jurisdiction_type' => $j->jurisdiction_type,
            'region' => $j->region,
            'subregion' => $j->subregion,
            'overview' => $j->overview,
            'regulatory_status_summary' => $j->regulatory_status_summary,
            'binding_vs_guidance' => $j->binding_vs_guidance,
            'current_priorities' => $j->current_priorities,
            'regulators' => $j->regulators ?? [],
            'official_sources' => $j->official_sources ?? [],
            'related_jurisdictions' => $j->related_jurisdictions ?? [],
            'url' => $j->url(),
            ...$this->sourceQuality($j),
        ];
        if ($withPolicies) {
            $out['policies'] = $j->policyInstruments->map(fn ($p) => $this->policySummary($p))->values()->all();
        }

        return $out;
    }

    public function policySummary(PolicyInstrument $p): array
    {
        return [
            'slug' => $p->slug,
            'title' => $p->title,
            'short_title' => $p->short_title,
            'jurisdiction' => $p->jurisdiction?->slug,
            'instrument_type' => $p->instrument_type,
            'status' => $p->status,
            'is_binding' => $p->is_binding,
            'issuing_body' => $p->issuing_body,
            'summary_plain' => $p->summary_plain,
            'adopted_on' => $p->adopted_on?->toDateString(),
            'in_force_on' => $p->in_force_on?->toDateString(),
            'applies_from' => $p->applies_from?->toDateString(),
            'official_source_url' => $p->official_source_url,
            'last_verified_at' => $p->last_verified_at?->toDateString(),
            'review_status' => $p->review_status,
            'confidence_level' => $p->confidence_level,
            'updated_at' => $p->updated_at?->toIso8601String(),
            'url' => $p->url(),
        ];
    }

    public function policy(PolicyInstrument $p): array
    {
        $p->loadMissing(['jurisdiction', 'terms', 'sections', 'obligations.terms', 'obligations.frameworkMappings', 'obligations.evidenceArtifacts', 'obligations.applicabilityRules', 'deadlines', 'versions', 'sourceDocuments', 'enforcementEvents', 'procurementRules', 'applicabilityRules']);

        return [
            ...$this->policySummary($p),
            'status_note' => $p->status_note,
            'scope_summary' => $p->scope_summary,
            'who_it_applies_to' => $p->who_it_applies_to,
            'key_dates_summary' => $p->key_dates_summary,
            'penalties_summary' => $p->penalties_summary,
            'what_organizations_must_do' => $p->what_organizations_must_do,
            'published_on' => $p->published_on?->toDateString(),
            'date_notes' => $p->date_notes,
            'taxonomy' => $p->terms->groupBy('taxonomy')->map(fn ($terms) => $terms->pluck('slug')->values())->all(),
            'sections' => $p->sections->map(fn ($s) => ['reference' => $s->reference, 'title' => $s->title, 'summary' => $s->summary, 'official_source_url' => $s->official_source_url])->all(),
            'obligations' => $p->obligations->map(fn ($o) => $this->obligation($o, false))->all(),
            'applicability_rules' => $p->applicabilityRules->whereNull('obligation_id')->map(fn ($r) => ['description' => $r->description, 'actors' => $r->actors, 'ai_system_types' => $r->ai_system_types, 'sectors' => $r->sectors, 'risk_categories' => $r->risk_categories, 'use_cases' => $r->use_cases, 'conditions' => $r->conditions, 'source_reference' => $r->source_reference])->values()->all(),
            'deadlines' => $p->deadlines->map(fn ($d) => ['title' => $d->title, 'due_on' => $d->due_on?->toDateString(), 'date_precision' => $d->date_precision, 'date_label' => $d->date_label, 'display_date' => $d->displayDate(), 'description' => $d->description, 'source_reference' => $d->source_reference, 'official_source_url' => $d->official_source_url, 'status' => $d->deadline_status, 'confidence_level' => $d->confidence_level])->all(),
            'versions' => $p->versions->map(fn ($v) => ['version_label' => $v->version_label, 'version_date' => $v->version_date?->toDateString(), 'summary' => $v->summary, 'official_source_url' => $v->official_source_url, 'source_reference' => $v->source_reference])->all(),
            'enforcement_events' => $p->enforcementEvents->map(fn ($e) => ['title' => $e->title, 'occurred_on' => $e->occurred_on?->toDateString(), 'authority' => $e->authority, 'summary' => $e->summary, 'outcome' => $e->outcome, 'official_source_url' => $e->official_source_url, 'confidence_level' => $e->confidence_level])->all(),
            'procurement_rules' => $p->procurementRules->map(fn ($r) => ['title' => $r->title, 'summary' => $r->summary, 'applies_to' => $r->applies_to, 'official_source_url' => $r->official_source_url, 'confidence_level' => $r->confidence_level])->all(),
            'sources' => $p->sourceDocuments->map(fn ($s) => ['title' => $s->title, 'publisher' => $s->publisher, 'url' => $s->url, 'document_date' => $s->document_date?->toDateString(), 'document_type' => $s->document_type, 'tier' => $s->source_tier, 'tier_label' => $s->tierLabel()])->all(),
            'related_policies' => $p->related_policies ?? [],
            'related_frameworks' => $p->related_frameworks ?? [],
            'faq' => $p->faq ?? [],
            ...$this->sourceQuality($p),
        ];
    }

    public function obligation(Obligation $o, bool $withPolicy = true): array
    {
        $out = [
            'slug' => $o->slug,
            'title' => $o->title,
            'category' => $o->category,
            'summary' => $o->summary,
            'practical_action' => $o->practical_action,
            'is_binding' => $o->is_binding,
            'applies_from' => $o->applies_from?->toDateString(),
            'section' => $o->section?->reference,
            'source_reference' => $o->source_reference,
            'official_source_url' => $o->official_source_url,
            'review_status' => $o->review_status,
            'confidence_level' => $o->confidence_level,
            'last_verified_at' => $o->last_verified_at?->toDateString(),
            'actors' => $o->terms->where('taxonomy', 'actor')->pluck('slug')->values()->all(),
            'sectors' => $o->terms->where('taxonomy', 'sector')->pluck('slug')->values()->all(),
            'use_cases' => $o->terms->where('taxonomy', 'use_case')->pluck('slug')->values()->all(),
            'applicability' => $o->applicabilityRules->map(fn ($r) => ['description' => $r->description, 'actors' => $r->actors, 'conditions' => $r->conditions])->values()->all(),
            'evidence_examples' => $o->evidenceArtifacts->map(fn ($e) => ['title' => $e->title, 'description' => $e->description, 'artifact_type' => $e->artifact_type])->all(),
            'framework_mappings' => $o->frameworkMappings->map(fn ($m) => ['framework' => $m->framework, 'framework_name' => $m->frameworkName(), 'reference' => $m->reference, 'note' => $m->note, 'confidence_level' => $m->confidence_level, 'is_original' => $m->is_original])->all(),
            'url' => $o->url(),
        ];
        if ($withPolicy && $o->relationLoaded('policyInstrument') && $o->policyInstrument) {
            $out['policy'] = ['slug' => $o->policyInstrument->slug, 'title' => $o->policyInstrument->title, 'status' => $o->policyInstrument->status, 'jurisdiction' => $o->policyInstrument->jurisdiction?->slug, 'url' => $o->policyInstrument->url()];
        }

        return $out;
    }

    public function change(ChangeEvent $c): array
    {
        return [
            'slug' => $c->slug,
            'occurred_on' => $c->occurred_on?->toDateString(),
            'jurisdiction' => $c->jurisdiction?->slug,
            'policy' => $c->policyInstrument?->slug,
            'title' => $c->title,
            'what_changed' => $c->what_changed,
            'practical_impact' => $c->practical_impact,
            'impact_level' => $c->impact_level,
            'status_after' => $c->status_after,
            'url' => $c->url(),
            ...$this->sourceQuality($c),
        ];
    }

    private function sourceQuality($m): array
    {
        return [
            'source' => [
                'official_source_url' => $m->official_source_url,
                'source_title' => $m->source_title,
                'source_publisher' => $m->source_publisher,
                'source_document_date' => $m->source_document_date?->toDateString(),
                'source_reference' => $m->source_reference,
                'source_tier' => $m->source_tier,
                'last_checked_at' => $m->last_checked_at?->toDateString(),
                'last_verified_at' => $m->last_verified_at?->toDateString(),
                'review_status' => $m->review_status,
                'confidence_level' => $m->confidence_level,
                'content_version' => $m->content_version,
                'change_summary' => $m->change_summary,
                'reviewed_by' => $m->reviewed_by,
            ],
        ];
    }
}
