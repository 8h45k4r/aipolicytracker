<?php

namespace App\Models;

use App\Models\Concerns\HasSourceQuality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A cited time series about AI-driven economic change. An empty series is a definition awaiting data. */
class TransitionIndicator extends Model
{
    use HasSourceQuality;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['series' => 'array', 'published_at' => 'datetime', 'last_checked_at' => 'datetime', 'last_verified_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function points(): array
    {
        $points = $this->series ?? [];
        usort($points, fn ($a, $b) => strcmp($a['period'], $b['period']));

        return $points;
    }

    public function latest(): ?array
    {
        $p = $this->points();

        return $p === [] ? null : end($p);
    }

    public function isDraft(): bool
    {
        return $this->review_status === 'draft';
    }
}
