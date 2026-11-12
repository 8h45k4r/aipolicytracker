<?php

namespace App\Models;

use Carbon\CarbonInterface;
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

    /** A comment period (closes on a date) or a public meeting, hearing or workshop (held on a date). */
    public const KINDS = ['comment_period' => 'Comment period', 'meeting' => 'Public meeting or hearing'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_on' => 'date', 'comments_close_on' => 'date', 'event_on' => 'date', 'reviewed_at' => 'datetime', 'fetched_at' => 'datetime'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Still open for comment today (a close date of today counts as open). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('kind', 'comment_period')->whereNotNull('comments_close_on')->where('comments_close_on', '>=', now()->toDateString());
    }

    /** A meeting or hearing still to come (today counts). */
    public function scopeUpcomingMeetings(Builder $query): Builder
    {
        return $query->where('kind', 'meeting')->whereNotNull('event_on')->where('event_on', '>=', now()->toDateString());
    }

    /** Either an open comment period or a meeting still to come: what the review queue and the calendar show. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->open())->orWhere(fn ($q) => $q->upcomingMeetings());
    }

    /** The date the entry is listed by: when comments close, or when the meeting is held. */
    public function listedOn(): ?CarbonInterface
    {
        return $this->kind === 'meeting' ? $this->event_on : $this->comments_close_on;
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }
}
