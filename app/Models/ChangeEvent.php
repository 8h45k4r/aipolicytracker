<?php

namespace App\Models;

use App\Enums\ImpactLevel;
use App\Enums\PolicyStatus;
use App\Models\Concerns\HasSourceQuality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeEvent extends Model
{
    use HasSourceQuality;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
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

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function policyInstrument(): BelongsTo
    {
        return $this->belongsTo(PolicyInstrument::class);
    }

    public function impactEnum(): ImpactLevel
    {
        return ImpactLevel::tryFrom((string) $this->impact_level) ?? ImpactLevel::Routine;
    }

    public function statusAfterEnum(): ?PolicyStatus
    {
        return $this->status_after ? PolicyStatus::tryFrom($this->status_after) : null;
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($term)).'%';

        return $query->where(function ($q) use ($like) {
            $q->whereRaw('LOWER(change_events.title) LIKE ?', [$like])
                ->orWhereRaw('LOWER(change_events.what_changed) LIKE ?', [$like]);
        });
    }
}
