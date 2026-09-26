<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One issue of the weekly digest, recorded when it is sent, so the newsletter
 * has an archive a search engine and a late reader can find. It holds the ids
 * of what the issue carried, not copies: the records keep their own pages, and
 * an issue page is built from them.
 */
class DigestIssue extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_on' => 'date', 'period_start' => 'date', 'period_end' => 'date', 'change_ids' => 'array', 'deadline_ids' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'sent_on';
    }

    /**
     * The date cast stores midnight ("2026-09-26 00:00:00"), so a plain equality
     * against the URL's "2026-09-26" misses on SQLite. Compare the date part.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? static::whereDate('sent_on', $value)->first() : null;
    }

    /** The issue for a day, created if there is none: one issue per send day. */
    public static function forDay(string $date): self
    {
        return static::whereDate('sent_on', $date)->first() ?? new static(['sent_on' => $date]);
    }

    public function url(): string
    {
        return route('newsletter.show', $this->sent_on->toDateString());
    }

    /** @return Collection<int,ChangeEvent> */
    public function changes(): Collection
    {
        $ids = $this->change_ids ?: [];

        return $ids === [] ? collect() : ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->whereIn('id', $ids)->orderByDesc('occurred_on')->get();
    }

    /** @return Collection<int,Deadline> */
    public function deadlines(): Collection
    {
        $ids = $this->deadline_ids ?: [];

        return $ids === [] ? collect() : Deadline::with('policyInstrument.jurisdiction')->whereIn('id', $ids)->orderBy('due_on')->get();
    }

    /** An issue is offered to an index when it carried enough to be worth a result. */
    public function isIndexable(): bool
    {
        return count($this->change_ids ?: []) >= 3;
    }
}
