<?php

namespace App\Models;

use App\Enums\InstrumentType;
use App\Enums\PolicyStatus;
use App\Models\Concerns\HasSourceQuality;
use App\Models\Concerns\HasTaxonomyTerms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyInstrument extends Model
{
    use HasSourceQuality;
    use HasTaxonomyTerms;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_binding' => 'boolean',
            'featured' => 'boolean',
            'faq' => 'array',
            'related_policies' => 'array',
            'related_frameworks' => 'array',
            'adopted_on' => 'date',
            'published_on' => 'date',
            'in_force_on' => 'date',
            'applies_from' => 'date',
            'source_document_date' => 'date',
            'last_checked_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function statusEnum(): PolicyStatus
    {
        return PolicyStatus::tryFrom((string) $this->status) ?? PolicyStatus::Archived;
    }

    public function typeEnum(): InstrumentType
    {
        return InstrumentType::tryFrom((string) $this->instrument_type) ?? InstrumentType::Policy;
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PolicyVersion::class)->orderBy('sort_order');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PolicySection::class)->orderBy('sort_order');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class)->orderBy('sort_order');
    }

    public function applicabilityRules(): HasMany
    {
        return $this->hasMany(ApplicabilityRule::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class)->orderBy('sort_order')->orderBy('due_on');
    }

    public function enforcementEvents(): HasMany
    {
        return $this->hasMany(EnforcementEvent::class);
    }

    public function procurementRules(): HasMany
    {
        return $this->hasMany(ProcurementRule::class);
    }

    public function changeEvents(): HasMany
    {
        return $this->hasMany(ChangeEvent::class)->orderByDesc('occurred_on');
    }

    public function sourceDocuments(): HasMany
    {
        return $this->hasMany(SourceDocument::class)->orderBy('sort_order');
    }

    /**
     * Indexation threshold: a policy page needs a plain-language summary, a scope
     * statement, and a primary official source before it may be indexed.
     */
    public function isIndexable(): bool
    {
        return filled($this->summary_plain)
            && filled($this->scope_summary)
            && filled($this->official_source_url)
            && $this->published_at !== null;
    }

    /** Display name for question headings, with a definite article where English needs one. */
    public function definiteName(): string
    {
        $name = $this->short_title ?: $this->title;

        return preg_match('/^(EU|UK|US|UAE|NIST|ICO|OMB|PDPC|Colorado|California|Australian|Singapore|Nepal|India)\b/', $name) ? 'the '.$name : $name;
    }

    public function url(): string
    {
        return route('policies.show', $this->slug);
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($term)).'%';

        return $query->where(function ($q) use ($like) {
            $q->whereRaw('LOWER(policy_instruments.title) LIKE ?', [$like])
                ->orWhereRaw('LOWER(policy_instruments.short_title) LIKE ?', [$like])
                ->orWhereRaw('LOWER(policy_instruments.summary_plain) LIKE ?', [$like])
                ->orWhereRaw('LOWER(policy_instruments.issuing_body) LIKE ?', [$like]);
        });
    }
}
