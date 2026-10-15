<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One issue of the weekly digest, recorded when it is sent, so the newsletter
 * has an archive a search engine and a late reader can find. Changes are held by
 * id (their rows and pages are permanent); upcoming dates are held by value, because
 * deadline rows are rebuilt on every import.
 */
class DigestIssue extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_on' => 'date', 'period_start' => 'date', 'period_end' => 'date', 'change_ids' => 'array', 'deadline_ids' => 'array', 'deadlines' => 'array'];
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

    /**
     * The dates as the issue carried them.
     *
     * @param  Collection<int,Deadline>  $deadlines
     * @return array<int, array{date: string, title: string, url: string, jurisdiction: ?string}>
     */
    public static function snapshotDeadlines(Collection $deadlines): array
    {
        return EloquentCollection::make($deadlines->all())->loadMissing('policyInstrument.jurisdiction')->map(fn (Deadline $d) => [
            'date' => $d->displayDate(),
            'title' => $d->title,
            'url' => $d->policyInstrument?->url(),
            'jurisdiction' => $d->policyInstrument?->jurisdiction?->name,
        ])->values()->all();
    }

    /** @return Collection<int, array{date: string, title: string, url: ?string, jurisdiction: ?string}> */
    public function deadlines(): Collection
    {
        if ($this->deadlines !== null) {
            return collect($this->deadlines);
        }
        // Issues sent before the snapshot: resolve the stored ids, published instruments only.
        $ids = $this->deadline_ids ?: [];

        return $ids === [] ? collect() : collect(self::snapshotDeadlines(
            Deadline::whereHas('policyInstrument', fn ($q) => $q->published())->whereIn('id', $ids)->orderBy('due_on')->get()
        ));
    }

    /** How many upcoming dates the issue carried. */
    public function deadlineCount(): int
    {
        return $this->deadlines !== null ? count($this->deadlines) : count($this->deadline_ids ?: []);
    }

    /** An issue is offered to an index when it carried enough to be worth a result. */
    public function isIndexable(): bool
    {
        return count($this->change_ids ?: []) >= 3;
    }
}
