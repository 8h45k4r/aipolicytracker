<?php

namespace App\Models;

use App\Models\Concerns\HasSourceQuality;
use App\Models\Concerns\HasTaxonomyTerms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Obligation extends Model
{
    use HasSourceQuality;
    use HasTaxonomyTerms;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_binding' => 'boolean',
            'applies_from' => 'date',
            'last_verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function policyInstrument(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(PolicySection::class, 'policy_section_id');
    }

    public function frameworkMappings(): HasMany
    {
        return $this->hasMany(FrameworkMapping::class);
    }

    public function evidenceArtifacts(): HasMany
    {
        return $this->hasMany(EvidenceArtifact::class);
    }

    public function applicabilityRules(): HasMany
    {
        return $this->hasMany(ApplicabilityRule::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class);
    }

    public function url(): string
    {
        return route('obligations.show', $this->slug);
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($term)).'%';

        return $query->where(function ($q) use ($like) {
            $q->whereRaw('LOWER(obligations.title) LIKE ?', [$like])
                ->orWhereRaw('LOWER(obligations.summary) LIKE ?', [$like]);
        });
    }
}
