<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public consultation or comment period on AI, from an official source.
 *
 * `collected`: fetched automatically and shown, labelled as not reviewed.
 * `confirmed`: a reviewer opened the official page and confirmed it is about AI and the date is right.
 * `hidden`: a reviewer decided it is not about AI governance; never shown.
 */
class Consultation extends Model
{
    public const STATUSES = ['collected', 'confirmed', 'hidden'];

    public const SOURCES = ['federal_register' => 'US Federal Register'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_on' => 'date', 'comments_close_on' => 'date', 'reviewed_at' => 'datetime', 'fetched_at' => 'datetime'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Still open for comment today (a close date of today counts as open). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotNull('comments_close_on')->where('comments_close_on', '>=', now()->toDateString());
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }
}
