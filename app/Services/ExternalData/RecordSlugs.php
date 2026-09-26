<?php

namespace App\Services\ExternalData;

use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Support\PageTitle;
use Illuminate\Support\Str;

/**
 * Readable addresses for incident and risk records, assigned once and never
 * changed.
 *
 * These records were published at their source keys: "/ai-risk/incidents/1382",
 * "/ai-risk/risks/05.17.00". The keys still resolve, as permanent redirects.
 *
 * A slug is written only into a row that has none. The importers upsert on the
 * source key and update only the columns they are given, and they are never
 * given this one, so a weekly re-import cannot move a published address. When a
 * database is built from scratch the result is still the same, because rows are
 * taken in source-key order and collisions are settled the same way each time.
 *
 * No slug carries an identifier. A collision is settled by adding what tells
 * the records apart to a reader: for an incident the year, then the month; for
 * a risk the paper's author, then the year. A counter is the last resort.
 */
final class RecordSlugs
{
    private const MAX_LENGTH = 72;

    /** @return int rows given a slug */
    public static function assignIncidents(): int
    {
        $taken = array_flip(ExternalIncident::whereNotNull('slug')->pluck('slug')->all());
        $assigned = 0;
        ExternalIncident::whereNull('slug')->orderBy('incident_id')->chunkById(500, function ($rows) use (&$taken, &$assigned) {
            foreach ($rows as $incident) {
                ExternalIncident::where('incident_id', $incident->incident_id)->update(['slug' => self::forIncident($incident, $taken)]);
                $assigned++;
            }
        }, 'incident_id');

        return $assigned;
    }

    /**
     * The address for one incident, given the addresses already taken. A
     * record about sexual imagery is addressed by its neutral name, so the
     * address does not repeat what the title no longer says.
     *
     * @param  array<string,int>|null  $taken  updated in place; null to ask the database
     */
    public static function forIncident(ExternalIncident $incident, ?array &$taken = null): string
    {
        if ($taken === null) {
            $taken = array_flip(ExternalIncident::whereNotNull('slug')->where('incident_id', '!=', $incident->incident_id)->pluck('slug')->all());
        }
        $base = self::slug(IncidentSensitivity::isSensitive($incident) ? IncidentSensitivity::neutralHeadline($incident) : (string) $incident->title) ?: 'ai-incident';
        $year = $incident->occurred_on?->format('Y');
        $month = $incident->occurred_on ? Str::lower($incident->occurred_on->format('M-Y')) : null;

        return self::unique($base, array_values(array_filter([$year ? "{$base}-{$year}" : null, $month ? "{$base}-{$month}" : null])), $taken);
    }

    /** @return int rows given a slug */
    public static function assignRisks(): int
    {
        $taken = array_flip(ExternalRisk::whereNotNull('slug')->pluck('slug')->all());
        $assigned = 0;
        // Paged by key, not by offset: every row updated leaves the "no slug
        // yet" set, and offset paging over a shrinking set skips rows.
        ExternalRisk::whereNull('slug')->chunkById(500, function ($rows) use (&$taken, &$assigned) {
            foreach ($rows as $risk) {
                ExternalRisk::where('ev_id', $risk->ev_id)->update(['slug' => self::forRisk($risk, $taken)]);
                $assigned++;
            }
        }, 'ev_id');

        return $assigned;
    }

    /**
     * The address for one risk entry, given the addresses already taken.
     *
     * @param  array<string,int>|null  $taken  updated in place; null to ask the database
     */
    public static function forRisk(ExternalRisk $risk, ?array &$taken = null): string
    {
        if ($taken === null) {
            $taken = array_flip(ExternalRisk::whereNotNull('slug')->where('ev_id', '!=', $risk->ev_id)->pluck('slug')->all());
        }
        $base = self::slug(PageTitle::riskName($risk)) ?: 'ai-risk';
        $cite = PageTitle::citation($risk->quick_ref);
        $author = $cite ? self::slug(explode(', ', $cite)[0]) : null;

        return self::unique($base, array_values(array_filter([
            $author ? "{$base}-{$author}" : null,
            $cite ? $base.'-'.self::slug($cite) : null,
        ])), $taken);
    }

    /**
     * A URL segment from a title: lowercase, hyphenated, cut at a word, and
     * never a word glued to a long number ("covid2019" becomes "covid-2019").
     */
    public static function slug(string $text): string
    {
        // A currency sign between a code and an amount ("CA$177,023") would
        // otherwise vanish and glue the two into "ca177023".
        $text = preg_replace('/\p{Sc}/u', ' ', $text);
        $text = preg_replace('/(\d),(\d{3})/', '$1$2', $text);
        $text = preg_replace('/(\p{L})(\d{4,})/u', '$1 $2', $text);
        $slug = Str::slug(Str::ascii($text));
        if (strlen($slug) > self::MAX_LENGTH) {
            $slug = substr($slug, 0, self::MAX_LENGTH);
            $slug = substr($slug, 0, strrpos($slug, '-') ?: self::MAX_LENGTH);
            // A cut address should not end mid-phrase ("...-due-to").
            $slug = preg_replace('/(-(?:a|an|and|as|at|by|due|for|from|in|into|of|on|or|over|the|to|via|with))+$/', '', $slug);
        }
        $slug = trim($slug, '-');

        // An all-number slug would read as the old numeric address.
        return preg_match('/^[0-9-]+$/', $slug) ? 'ai-'.$slug : $slug;
    }

    /**
     * @param  list<string>  $alternatives  tried in order after the base
     * @param  array<string,int>  $taken  updated in place
     */
    private static function unique(string $base, array $alternatives, array &$taken): string
    {
        foreach ([$base, ...$alternatives] as $candidate) {
            if (! isset($taken[$candidate])) {
                $taken[$candidate] = 1;

                return $candidate;
            }
        }
        $last = end($alternatives) ?: $base;
        for ($n = 2; isset($taken["{$last}-{$n}"]); $n++);
        $taken["{$last}-{$n}"] = 1;

        return "{$last}-{$n}";
    }
}
