<?php

namespace App\Support;

use App\Models\RecordRevision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * When a record's content last changed, as opposed to when its row was last written.
 *
 * updated_at is the second. It moves whenever an import writes the row for any
 * reason: a bulk edit that adds a field to every file, a link check that refreshes
 * last_checked_at, an admin verification re-applied over the file's values. The
 * sitemap carried it as lastmod, so every policy page claimed to have changed on one
 * of three deploy times, and an index that catches a lastmod moving with nothing
 * behind it stops believing any of them.
 *
 * The content date is the latest of:
 *  - last_verified_at: a named reviewer confirmed the record against its source,
 *    and the page says so with that date;
 *  - the newest record_revisions entry: the importer writes one only when a field
 *    a reader sees (title, status, dates, summary, source) actually differs;
 *  - published_at: the day the record first went public, which the importer keeps
 *    from the row already there. It is the floor: a page has not changed since
 *    before it existed, and it is the only date a never-revised record has.
 * Nothing in this list moves on a deploy that changes nothing a reader can see.
 */
final class ContentDates
{
    /**
     * The newest revision time per record, for one record type.
     *
     * @param  string  $type  a RecordRevision record_type: policy, obligation, change or control
     * @return array<string, Carbon> slug => changed_at
     */
    public static function revisions(string $type): array
    {
        return RecordRevision::query()->where('record_type', $type)
            ->selectRaw('record_slug, max(changed_at) as revised_at')->groupBy('record_slug')
            ->pluck('revised_at', 'record_slug')
            ->map(fn ($at) => Carbon::parse($at))->all();
    }

    /** One record's content date. $revised is its entry from revisions(), if any. */
    public static function of(Model $record, ?\DateTimeInterface $revised = null): ?Carbon
    {
        $dates = array_filter([$record->getAttribute('last_verified_at'), $revised, $record->getAttribute('published_at')]);

        return $dates === [] ? null : Carbon::instance(max($dates));
    }

    /**
     * The newest content date across a published record type: what a listing or a
     * sitemap section built from those records last changed.
     *
     * @param  class-string<Model>  $model  a record model with the published() scope
     * @param  string|null  $type  its RecordRevision record_type, when it has revisions
     */
    public static function latest(string $model, ?string $type = null): ?Carbon
    {
        $dates = array_filter([
            $model::query()->published()->max('last_verified_at'),
            $model::query()->published()->max('published_at'),
            $type ? RecordRevision::query()->where('record_type', $type)->max('changed_at') : null,
        ]);

        return $dates === [] ? null : max(array_map(fn ($d) => Carbon::parse($d), $dates));
    }
}
