<?php

namespace App\Models;

use App\Models\Concerns\HasSourceQuality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An organisational control: what an organisation operates to meet one or more
 * legal duties, the evidence it produces, the risks it addresses and the
 * standards clauses it corresponds to. Records live in data/controls/*.yaml.
 */
class Control extends Model
{
    use HasSourceQuality;

    public const KINDS = [
        'policy' => 'Policy',
        'process' => 'Process',
        'technical' => 'Technical measure',
        'contractual' => 'Contractual term',
        'training' => 'Training programme',
    ];

    public const FREQUENCIES = [
        'once' => 'Once, then maintained',
        'per_system' => 'Once per AI system',
        'on_material_change' => 'At launch and on material change',
        'continuous' => 'Continuous',
        'quarterly' => 'Quarterly',
        'annual' => 'Annual',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'risk_subdomains' => 'array',
            'related_controls' => 'array',
            'last_verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ControlEvidence::class)->orderBy('sort_order');
    }

    public function frameworkReferences(): HasMany
    {
        return $this->hasMany(ControlFrameworkReference::class);
    }

    public function obligations(): BelongsToMany
    {
        return $this->belongsToMany(Obligation::class)->withPivot(['relationship', 'note', 'confidence_level'])->withTimestamps();
    }

    public function url(): string
    {
        return route('controls.show', $this->slug);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    public function frequencyLabel(): string
    {
        return self::FREQUENCIES[$this->frequency] ?? str_replace('_', ' ', (string) $this->frequency);
    }

    /** A control is worth indexing once it does real work: a purpose, evidence and at least one duty. */
    public function isIndexable(): bool
    {
        return $this->published_at !== null && filled($this->purpose) && $this->evidence->isNotEmpty() && $this->obligations->isNotEmpty();
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($term)).'%';

        return $query->where(function ($q) use ($like) {
            $q->whereRaw('LOWER(controls.title) LIKE ?', [$like])
                ->orWhereRaw('LOWER(controls.purpose) LIKE ?', [$like]);
        });
    }
}
