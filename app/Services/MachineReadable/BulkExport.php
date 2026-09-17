<?php

namespace App\Services\MachineReadable;

use App\Models\ChangeEvent;
use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Flat, whole-corpus exports: one row per record, in CSV for a spreadsheet and
 * newline-delimited JSON for a pipeline.
 *
 * The existing bundle at /open-data/aipolicytracker-latest.json is a nested
 * document you have to load whole. These are the other shape: streamable, one
 * self-contained row at a time, which is what a script or an agent working
 * through the corpus actually wants.
 *
 * Every row carries its own provenance columns — source, review status,
 * confidence, last confirmed — because a row that travels on its own without
 * them invites somebody to treat an unverified record as a fact.
 */
class BulkExport
{
    /** @var array<string, array{label: string, model: class-string<Model>, with: list<string>}> */
    public const DATASETS = [
        'jurisdictions' => ['label' => 'Jurisdiction profiles', 'model' => Jurisdiction::class, 'with' => []],
        'policies' => ['label' => 'Policy instruments', 'model' => PolicyInstrument::class, 'with' => ['jurisdiction']],
        'obligations' => ['label' => 'Obligations', 'model' => Obligation::class, 'with' => ['policyInstrument.jurisdiction']],
        'changes' => ['label' => 'Change log entries', 'model' => ChangeEvent::class, 'with' => ['jurisdiction', 'policyInstrument']],
        'deadlines' => ['label' => 'Dated deadlines', 'model' => Deadline::class, 'with' => ['policyInstrument.jurisdiction']],
    ];

    public function exists(string $dataset): bool
    {
        return array_key_exists($dataset, self::DATASETS);
    }

    /** @return list<string> */
    public function columns(string $dataset): array
    {
        return array_keys($this->row($this->blank($dataset)));
    }

    /** Rows, streamed in chunks so the whole corpus is never held in memory at once. */
    public function each(string $dataset, callable $callback): void
    {
        $this->query($dataset)->chunk(200, function ($records) use ($callback) {
            foreach ($records as $record) {
                $callback($this->row($record));
            }
        });
    }

    private function query(string $dataset): Builder
    {
        $meta = self::DATASETS[$dataset];
        /** @var class-string<Model> $class */
        $class = $meta['model'];

        // Deadlines have no published flag of their own: they are published exactly when
        // their instrument is, which is the only place that fact lives.
        $query = $dataset === 'deadlines'
            ? $class::query()->whereHas('policyInstrument', fn ($q) => $q->published())
            : $class::query()->published();

        return $query->with($meta['with'])->orderBy('id');
    }

    private function blank(string $dataset): Model
    {
        /** @var class-string<Model> $class */
        $class = self::DATASETS[$dataset]['model'];

        return new $class;
    }

    /** @return array<string, string|int|bool|null> */
    private function row(Model $r): array
    {
        return match (true) {
            $r instanceof Jurisdiction => [
                'slug' => $r->slug,
                'name' => $r->name,
                'short_name' => $r->short_name,
                'iso_code' => $r->iso_code,
                'jurisdiction_type' => $r->jurisdiction_type,
                'region' => $r->region,
                'subregion' => $r->subregion,
                'regulatory_status_summary' => $this->text($r->regulatory_status_summary),
                'binding_vs_guidance' => $this->text($r->binding_vs_guidance),
                'regulators' => $this->list($r->regulators),
                'official_sources' => $this->list($r->official_sources),
                'url' => $r->exists ? $r->url() : null,
                'context_url' => $r->exists ? route('jurisdictions.context', $r->slug) : null,
            ] + $this->provenance($r),
            $r instanceof PolicyInstrument => [
                'slug' => $r->slug,
                'title' => $r->title,
                'short_title' => $r->short_title,
                'jurisdiction' => $r->jurisdiction?->slug,
                'jurisdiction_name' => $r->jurisdiction?->name,
                'instrument_type' => $r->instrument_type,
                'status' => $r->status,
                'is_binding' => (bool) $r->is_binding,
                'issuing_body' => $r->issuing_body,
                'adopted_on' => $this->date($r->adopted_on),
                'published_on' => $this->date($r->published_on),
                'in_force_on' => $this->date($r->in_force_on),
                'applies_from' => $this->date($r->applies_from),
                'summary_plain' => $this->text($r->summary_plain),
                'who_it_applies_to' => $this->text($r->who_it_applies_to),
                'url' => $r->exists ? $r->url() : null,
                'context_url' => $r->exists ? route('policies.context', $r->slug) : null,
            ] + $this->provenance($r),
            $r instanceof Obligation => [
                'slug' => $r->slug,
                'title' => $r->title,
                'policy' => $r->policyInstrument?->slug,
                'jurisdiction' => $r->policyInstrument?->jurisdiction?->slug,
                'category' => $r->category,
                'is_binding' => (bool) $r->is_binding,
                'applies_from' => $this->date($r->applies_from),
                'summary' => $this->text($r->summary),
                'practical_action' => $this->text($r->practical_action),
                'url' => $r->exists ? $r->url() : null,
                'context_url' => $r->exists ? route('obligations.context', $r->slug) : null,
            ] + $this->provenance($r),
            $r instanceof ChangeEvent => [
                'slug' => $r->slug,
                'title' => $r->title,
                'occurred_on' => $this->date($r->occurred_on),
                'jurisdiction' => $r->jurisdiction?->slug,
                'policy' => $r->policyInstrument?->slug,
                'impact_level' => $r->impact_level,
                'status_after' => $r->status_after,
                'what_changed' => $this->text($r->what_changed),
                'practical_impact' => $this->text($r->practical_impact),
                'url' => $r->exists && $r->occurred_on ? route('changes.year', $r->occurred_on->year).'#'.$r->slug : null,
                'context_url' => $r->exists ? route('changes.context', $r->slug) : null,
            ] + $this->provenance($r),
            $r instanceof Deadline => [
                'policy' => $r->policyInstrument?->slug,
                'jurisdiction' => $r->policyInstrument?->jurisdiction?->slug,
                'title' => $r->title,
                'due_on' => $this->date($r->due_on),
                'date_precision' => $r->date_precision,
                'date_label' => $r->date_label,
                'deadline_status' => $r->deadline_status,
                'description' => $this->text($r->description),
                'source_reference' => $r->source_reference,
                'official_source_url' => $r->official_source_url,
                'confidence_level' => $r->confidence_level,
                'url' => $r->policyInstrument?->url(),
            ],
            default => [],
        };
    }

    /** @return array<string, string|null> */
    private function provenance(Model $r): array
    {
        return [
            'official_source_url' => $r->official_source_url,
            'source_publisher' => $r->source_publisher ?? null,
            'source_reference' => $r->source_reference ?? null,
            'review_status' => $r->review_status,
            'confidence_level' => $r->confidence_level,
            'last_verified_at' => $this->date($r->last_verified_at),
            'reviewed_by' => $r->reviewed_by ?? null,
        ];
    }

    private function date(mixed $value): ?string
    {
        return blank($value) ? null : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value);
    }

    /**
     * A stored list stays a list.
     *
     * A jurisdiction's regulators and official sources are lists of objects, each
     * carrying a link that makes it checkable. Newline-delimited JSON keeps them
     * nested; CSV, which has no nesting, encodes the cell as JSON rather than
     * flattening away the links.
     */
    private function list(mixed $value): ?array
    {
        return is_array($value) && $value !== [] ? $value : null;
    }

    /** Newlines inside a cell break naive CSV readers, so stored paragraphs are flattened. */
    private function text(?string $value): ?string
    {
        return $value === null ? null : trim(preg_replace('/\s*\R\s*/u', ' ', $value) ?? $value);
    }
}
