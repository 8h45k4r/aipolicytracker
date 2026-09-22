<?php

namespace App\Models;

use App\Enums\ImpactLevel;
use App\Enums\PolicyStatus;
use App\Models\Concerns\HasSourceQuality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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

    /** The permanent address of this change: what a feed, a digest and a citation point at. */
    public function url(): string
    {
        return route('changes.show', $this->slug);
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

    /**
     * Distinct years (newest first) of published change events.
     * Computed in PHP so it works on PostgreSQL, MySQL and SQLite alike
     * (substr() on a DATE column is not portable).
     *
     * @return array<int, string>
     */
    public static function publishedYears(): array
    {
        return static::published()
            ->pluck('occurred_on')
            ->map(fn ($d) => substr((string) $d, 0, 4))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * Year => latest updated_at among published change events in that year.
     *
     * @return Collection<string, mixed>
     */
    public static function publishedYearsLastModified(): Collection
    {
        return static::published()
            ->get(['occurred_on', 'updated_at'])
            ->groupBy(fn ($e) => substr((string) $e->occurred_on, 0, 4))
            ->map(fn ($group) => $group->max('updated_at'))
            ->sortKeysDesc();
    }
}
