<?php

namespace App\Services\PolicyData;

use App\Models\ApplicabilityRule;
use App\Models\ChangeEvent;
use App\Models\Deadline;
use App\Models\EnforcementEvent;
use App\Models\EvidenceArtifact;
use App\Models\FrameworkMapping;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\PolicySection;
use App\Models\PolicyVersion;
use App\Models\ProcurementRule;
use App\Models\SourceDocument;
use App\Models\TaxonomyTerm;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent import of data/ records into the relational read model.
 * Child rows (sections, obligations, deadlines, ...) are replaced wholesale for
 * each policy so the YAML file remains the single source of truth.
 */
class PolicyImporter
{
    /** @var array<string, array<string, TaxonomyTerm>> */
    private array $terms = [];

    private array $stats = ['jurisdictions' => 0, 'policies' => 0, 'obligations' => 0, 'changes' => 0, 'terms' => 0];

    public function __construct(private readonly PolicyDataRepository $repository) {}

    public function run(): array
    {
        DB::transaction(function () {
            $this->importTaxonomies();
            $this->importJurisdictions();
            $this->importPolicies();
            $this->importChanges();
        });

        return $this->stats;
    }

    private function importTaxonomies(): void
    {
        foreach ($this->repository->taxonomies() as $taxonomy => $terms) {
            foreach ($terms as $i => $term) {
                $model = TaxonomyTerm::updateOrCreate(
                    ['taxonomy' => $taxonomy, 'slug' => $term['slug']],
                    ['name' => $term['name'], 'description' => $term['description'] ?? null, 'sort_order' => $i]
                );
                $this->terms[$taxonomy][$term['slug']] = $model;
                $this->stats['terms']++;
            }
        }
    }

    private function importJurisdictions(): void
    {
        $records = $this->repository->jurisdictions();
        // Two passes so parent references resolve regardless of file order.
        foreach ($records as $record) {
            Jurisdiction::updateOrCreate(['slug' => $record['slug']], [
                'name' => $record['name'],
                'short_name' => $record['short_name'] ?? null,
                'iso_code' => $record['iso_code'] ?? null,
                'jurisdiction_type' => $record['jurisdiction_type'],
                'region' => $record['region'],
                'subregion' => $record['subregion'] ?? null,
                'overview' => $record['overview'],
                'regulatory_status_summary' => $record['regulatory_status_summary'],
                'binding_vs_guidance' => $record['binding_vs_guidance'] ?? null,
                'current_priorities' => $record['current_priorities'] ?? null,
                'how_to_use' => $record['how_to_use'] ?? [],
                'regulators' => $record['regulators'] ?? [],
                'official_sources' => $record['official_sources'] ?? [],
                'faq' => $record['faq'] ?? [],
                'related_jurisdictions' => $record['related_jurisdictions'] ?? [],
                'featured' => (bool) ($record['featured'] ?? false),
                'published_at' => $this->publishedAt($record),
                ...$this->sourceQuality($record),
            ]);
            $this->stats['jurisdictions']++;
        }
        foreach ($records as $record) {
            if (! empty($record['parent'])) {
                $parent = Jurisdiction::where('slug', $record['parent'])->first();
                Jurisdiction::where('slug', $record['slug'])->update(['parent_jurisdiction_id' => $parent?->id]);
            }
        }
    }

    private function importPolicies(): void
    {
        foreach ($this->repository->policies() as $file => $record) {
            $jurisdiction = Jurisdiction::where('slug', $record['jurisdiction'])->firstOrFail();

            $policy = PolicyInstrument::updateOrCreate(['slug' => $record['slug']], [
                'jurisdiction_id' => $jurisdiction->id,
                'title' => $record['title'],
                'short_title' => $record['short_title'] ?? null,
                'instrument_type' => $record['instrument_type'],
                'status' => $record['status'],
                'status_note' => $record['status_note'] ?? null,
                'is_binding' => (bool) $record['is_binding'],
                'issuing_body' => $record['issuing_body'],
                'summary_plain' => $record['summary_plain'],
                'scope_summary' => $record['scope_summary'],
                'who_it_applies_to' => $record['who_it_applies_to'] ?? null,
                'key_dates_summary' => $record['key_dates_summary'] ?? null,
                'penalties_summary' => $record['penalties_summary'] ?? null,
                'what_organizations_must_do' => $record['what_organizations_must_do'] ?? null,
                'adopted_on' => $record['adopted_on'] ?? null,
                'published_on' => $record['published_on'] ?? null,
                'in_force_on' => $record['in_force_on'] ?? null,
                'applies_from' => $record['applies_from'] ?? null,
                'date_notes' => $record['date_notes'] ?? null,
                'faq' => $record['faq'] ?? [],
                'related_policies' => $record['related_policies'] ?? [],
                'related_frameworks' => $record['related_frameworks'] ?? [],
                'featured' => (bool) ($record['featured'] ?? false),
                'published_at' => $this->publishedAt($record),
                ...$this->sourceQuality($record),
            ]);

            $this->syncTerms($policy, $record);

            $policy->versions()->delete();
            foreach ($record['versions'] ?? [] as $i => $version) {
                PolicyVersion::create(['policy_instrument_id' => $policy->id, 'sort_order' => $i, ...Arr::only($version, ['version_label', 'version_date', 'summary', 'official_source_url', 'source_reference'])]);
            }

            $policy->sections()->delete();
            $sections = [];
            foreach ($record['sections'] ?? [] as $i => $section) {
                $sections[$section['reference']] = PolicySection::create(['policy_instrument_id' => $policy->id, 'sort_order' => $i, ...Arr::only($section, ['reference', 'title', 'summary', 'official_source_url'])]);
            }

            // Obligations are keyed by slug so their public URLs stay stable.
            $keptObligationIds = [];
            $obligationsBySlug = [];
            foreach ($record['obligations'] ?? [] as $i => $item) {
                $obligation = Obligation::updateOrCreate(['slug' => $item['slug']], [
                    'policy_instrument_id' => $policy->id,
                    'policy_section_id' => isset($item['section'], $sections[$item['section']]) ? $sections[$item['section']]->id : null,
                    'title' => $item['title'],
                    'category' => $item['category'],
                    'summary' => $item['summary'],
                    'practical_action' => $item['practical_action'] ?? null,
                    'is_binding' => (bool) $item['is_binding'],
                    'applies_from' => $item['applies_from'] ?? null,
                    'source_reference' => $item['source_reference'] ?? null,
                    'official_source_url' => $item['official_source_url'] ?? $record['official_source_url'],
                    'last_verified_at' => $item['last_verified_at'] ?? null,
                    'review_status' => $item['review_status'] ?? $record['review_status'],
                    'confidence_level' => $item['confidence_level'] ?? $record['confidence_level'],
                    'sort_order' => $i,
                    'published_at' => $policy->published_at,
                ]);
                $keptObligationIds[] = $obligation->id;
                $obligationsBySlug[$item['slug']] = $obligation;
                $this->syncTerms($obligation, $item + ['obligation_categories' => [$item['category']]]);

                $obligation->frameworkMappings()->delete();
                foreach ($item['framework_mappings'] ?? [] as $mapping) {
                    FrameworkMapping::create(['obligation_id' => $obligation->id, 'is_original' => true, 'confidence_level' => $mapping['confidence_level'] ?? 'medium', ...Arr::only($mapping, ['framework', 'reference', 'note'])]);
                }
                $obligation->evidenceArtifacts()->delete();
                foreach ($item['evidence_examples'] ?? [] as $evidence) {
                    EvidenceArtifact::create(['obligation_id' => $obligation->id, 'artifact_type' => $evidence['artifact_type'] ?? 'document', ...Arr::only($evidence, ['title', 'description'])]);
                }
                $obligation->applicabilityRules()->delete();
                if (! empty($item['applicability'])) {
                    ApplicabilityRule::create(['policy_instrument_id' => $policy->id, 'obligation_id' => $obligation->id, ...Arr::only($item['applicability'], ['description', 'actors', 'ai_system_types', 'sectors', 'risk_categories', 'use_cases', 'conditions', 'source_reference'])]);
                }
                $this->stats['obligations']++;
            }
            Obligation::where('policy_instrument_id', $policy->id)->whereNotIn('id', $keptObligationIds)->delete();

            $policy->applicabilityRules()->whereNull('obligation_id')->delete();
            foreach ($record['applicability_rules'] ?? [] as $rule) {
                ApplicabilityRule::create(['policy_instrument_id' => $policy->id, ...Arr::only($rule, ['description', 'actors', 'ai_system_types', 'sectors', 'risk_categories', 'use_cases', 'conditions', 'source_reference'])]);
            }

            $policy->deadlines()->delete();
            foreach ($record['deadlines'] ?? [] as $i => $deadline) {
                Deadline::create([
                    'policy_instrument_id' => $policy->id,
                    'obligation_id' => isset($deadline['obligation'], $obligationsBySlug[$deadline['obligation']]) ? $obligationsBySlug[$deadline['obligation']]->id : null,
                    'sort_order' => $i,
                    'deadline_status' => $deadline['deadline_status'] ?? (isset($deadline['due_on']) && $deadline['due_on'] < now()->toDateString() ? 'passed' : 'scheduled'),
                    'confidence_level' => $deadline['confidence_level'] ?? $record['confidence_level'],
                    ...Arr::only($deadline, ['title', 'due_on', 'date_precision', 'date_label', 'description', 'source_reference', 'official_source_url']),
                ]);
            }

            $policy->enforcementEvents()->delete();
            foreach ($record['enforcement_events'] ?? [] as $event) {
                EnforcementEvent::create(['jurisdiction_id' => $jurisdiction->id, 'policy_instrument_id' => $policy->id, 'published_at' => $policy->published_at, ...Arr::only($event, ['title', 'occurred_on', 'authority', 'summary', 'outcome', 'official_source_url', 'source_title', 'source_publisher', 'source_reference', 'confidence_level', 'review_status'])]);
            }

            $policy->procurementRules()->delete();
            foreach ($record['procurement_rules'] ?? [] as $rule) {
                ProcurementRule::create(['jurisdiction_id' => $jurisdiction->id, 'policy_instrument_id' => $policy->id, 'published_at' => $policy->published_at, ...Arr::only($rule, ['title', 'summary', 'applies_to', 'official_source_url', 'source_title', 'source_publisher', 'source_reference', 'confidence_level', 'review_status'])]);
            }

            $policy->sourceDocuments()->delete();
            foreach ($record['sources'] ?? [] as $i => $source) {
                SourceDocument::create([
                    'jurisdiction_id' => $jurisdiction->id,
                    'policy_instrument_id' => $policy->id,
                    'sort_order' => $i,
                    'title' => $source['title'],
                    'publisher' => $source['publisher'],
                    'url' => $source['url'],
                    'document_date' => $source['document_date'] ?? null,
                    'document_type' => $source['document_type'] ?? 'official',
                    'source_tier' => $source['tier'],
                    'language' => $source['language'] ?? 'en',
                    'license_note' => $source['license_note'] ?? null,
                ]);
            }

            $this->stats['policies']++;
        }
    }

    private function importChanges(): void
    {
        foreach ($this->repository->changeFiles() as $contents) {
            foreach ($contents['changes'] ?? [] as $change) {
                $jurisdiction = Jurisdiction::where('slug', $change['jurisdiction'])->firstOrFail();
                $policy = ! empty($change['policy']) ? PolicyInstrument::where('slug', $change['policy'])->first() : null;
                ChangeEvent::updateOrCreate(['slug' => $change['slug']], [
                    'jurisdiction_id' => $jurisdiction->id,
                    'policy_instrument_id' => $policy?->id,
                    'occurred_on' => $change['occurred_on'],
                    'title' => $change['title'],
                    'what_changed' => $change['what_changed'],
                    'practical_impact' => $change['practical_impact'] ?? null,
                    'impact_level' => $change['impact_level'],
                    'status_after' => $change['status_after'] ?? null,
                    'published_at' => $this->publishedAt($change),
                    ...$this->sourceQuality($change),
                ]);
                $this->stats['changes']++;
            }
        }
    }

    private function syncTerms(PolicyInstrument|Obligation $model, array $record): void
    {
        $ids = [];
        foreach (['actors' => 'actor', 'ai_system_types' => 'ai_system_type', 'sectors' => 'sector', 'risk_categories' => 'risk_category', 'use_cases' => 'use_case', 'obligation_categories' => 'obligation_category'] as $field => $taxonomy) {
            foreach ($record[$field] ?? [] as $slug) {
                if (isset($this->terms[$taxonomy][$slug])) {
                    $ids[] = $this->terms[$taxonomy][$slug]->id;
                }
            }
        }
        $model->terms()->sync($ids);
    }

    private function sourceQuality(array $record): array
    {
        return [
            'official_source_url' => $record['official_source_url'] ?? null,
            'source_title' => $record['source_title'] ?? null,
            'source_publisher' => $record['source_publisher'] ?? null,
            'source_document_date' => $record['source_document_date'] ?? null,
            'source_reference' => $record['source_reference'] ?? null,
            'source_tier' => $record['source_tier'] ?? 1,
            'last_checked_at' => $record['last_checked_at'] ?? null,
            'last_verified_at' => $record['last_verified_at'] ?? null,
            'review_status' => $record['review_status'] ?? 'pending_review',
            'confidence_level' => $record['confidence_level'] ?? 'medium',
            'content_version' => $record['content_version'] ?? 1,
            'change_summary' => $record['change_summary'] ?? null,
            'reviewed_by' => $record['reviewed_by'] ?? null,
        ];
    }

    private function publishedAt(array $record): ?\Illuminate\Support\Carbon
    {
        return ($record['published'] ?? true) ? now() : null;
    }
}
