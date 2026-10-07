<?php

namespace App\Models;

use App\Models\Concerns\HasSourceQuality;
use App\Services\Implementation\ImplementationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A measure an instrument depends on to work in practice (guidelines, codes of
 * practice, delegated and implementing acts, templates, standards). Imported from
 * data/implementation. Standards are metadata only. A draft has every factual field
 * null; it is listed so the gap is visible and never counted as recorded.
 */
class ImplementationMeasure extends Model
{
    use HasSourceQuality;

    public const KINDS = [
        'delegated_act' => 'Delegated act', 'implementing_act' => 'Implementing act', 'guidelines' => 'Guidelines',
        'code_of_practice' => 'Code of practice', 'template' => 'Template', 'ai_board_output' => 'AI Board output',
        'standardisation_request' => 'Standardisation request', 'harmonised_standard' => 'Harmonised standard', 'iso_work_item' => 'ISO/IEC standard',
    ];

    /** The kinds the standards tracker lists. */
    public const STANDARD_KINDS = ['harmonised_standard', 'iso_work_item', 'standardisation_request'];

    public const BODIES = [
        'cen_cenelec_jtc_21' => 'CEN-CENELEC JTC 21', 'iso_iec_jtc_1_sc_42' => 'ISO/IEC JTC 1/SC 42',
        'european_commission' => 'European Commission', 'ai_office' => 'AI Office', 'ai_board' => 'AI Board',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_on' => 'date', 'adopted_on' => 'date', 'published_on' => 'date', 'oj_citation_expected_on' => 'date', 'oj_citation_on' => 'date',
            'source_document_date' => 'date', 'last_checked_at' => 'datetime', 'last_verified_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    public function policyInstrument(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class);
    }

    public function relatedPolicy(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class, 'related_policy_instrument_id');
    }

    public function isDraft(): bool
    {
        return $this->review_status === 'draft' || $this->status === 'unverified';
    }

    public function isStandard(): bool
    {
        return in_array($this->kind, self::STANDARD_KINDS, true);
    }

    /** Declared status, or "overdue" when the due date has passed with nothing adopted. */
    public function effectiveStatus(?string $today = null): string
    {
        return ImplementationStatus::derive((string) $this->status, $this->due_on?->toDateString(), $this->adopted_on?->toDateString(), $this->published_on?->toDateString(), $today ?? now()->toDateString());
    }

    public function statusLabel(): string
    {
        return ImplementationStatus::label($this->effectiveStatus());
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst(str_replace('_', ' ', (string) $this->kind));
    }

    public function bodyLabel(): ?string
    {
        return $this->body ? (self::BODIES[$this->body] ?? $this->body) : null;
    }

    /** The publication date at the precision the source gives it. */
    public function publishedLabel(): ?string
    {
        if (! $this->published_on) {
            return null;
        }

        return match ($this->published_on_precision) {
            'year' => $this->published_on->format('Y'),
            'month' => $this->published_on->format('F Y'),
            default => $this->published_on->format('j M Y'),
        };
    }

    /** Where the measure is listed: on its instrument's implementation page, or the standards tracker. */
    public function url(): string
    {
        $anchor = '#'.$this->slug;
        if ($this->policyInstrument && ! $this->isStandard()) {
            return route('policies.implementation', $this->policyInstrument->slug).$anchor;
        }

        return route('standards.index').$anchor;
    }
}
