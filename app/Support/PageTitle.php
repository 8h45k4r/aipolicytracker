<?php

namespace App\Support;

use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\ExternalData\IncidentSensitivity;
use Illuminate\Support\Facades\Cache;

/**
 * Every page title on this site, and the rules they are held to, in one place.
 *
 * Search Console showed hundreds of queries shaped like "infocomm2023": an
 * internal reference glued to a word. They came from titles that carried a
 * record's source key rather than its name — the MIT repository's citation keys
 * on 1,617 risk pages, "#1382" on 1,663 incident pages. A title is a promise
 * about what the page answers; a key promises nothing a person would search for.
 *
 * The budget is the rendered <title>, brand included. The brand is added only
 * when it still fits (see withBrand()): search results show the site name on
 * their own line, taken from the WebSite node every page already publishes, so
 * a suffix that pushes the useful words past the cut-off buys nothing.
 *
 * Each entity has an ordered list of tails, tried longest first. When even the
 * bare name is over budget it is cut at a clause boundary, or failing that at a
 * word boundary with an ellipsis — never through the middle of a word.
 */
final class PageTitle
{
    /** The rendered <title>, brand suffix included. */
    public const MAX = 60;

    public const MAX_DESCRIPTION = 155;

    /** A clause shorter than this is not worth keeping as a title on its own. */
    private const MIN_CLAUSE = 24;

    // ---------------------------------------------------------------- rules

    /**
     * A word run straight into four or more digits ("infocomm2023"), or a
     * hash-number reference ("#1382"). A year standing on its own ("AI Act
     * 2024") is not an identifier and does not match.
     */
    public static function leaksIdentifier(string $text): bool
    {
        $t = mb_strtolower($text);

        return (bool) preg_match('/\b[\p{Ll}]+\d{4,}\b/u', $t) || (bool) preg_match('/#\d{2,}\b/', $t);
    }

    /**
     * A URL path whose record segment is only a number or a dotted code
     * ("/ai-risk/incidents/1382", "/ai-risk/risks/05.17.00"), or which carries
     * a glued identifier. A year archive ("/changes/2026") is a date, not an
     * identifier, and is allowed.
     */
    public static function pathLeaksIdentifier(string $path): bool
    {
        foreach (explode('/', trim($path, '/')) as $segment) {
            if (preg_match('/^(19|20)\d{2}$/', $segment)) {
                continue;
            }
            if (preg_match('/^[\d.]+$/', $segment) || self::leaksIdentifier($segment)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------ mechanics

    /**
     * The first "$name$tail" that fits, else the name cut to fit.
     *
     * @param  list<string>  $tails  tried in order; '' is implied last
     */
    public static function fit(string $name, array $tails = [], int $max = self::MAX): string
    {
        $name = self::clean($name);
        foreach ($tails as $tail) {
            if ($tail !== '' && mb_strlen($name.$tail) <= $max) {
                return $name.$tail;
            }
        }

        return self::shorten($name, $max);
    }

    /** "Title | Brand" when that fits, otherwise the title alone. */
    public static function withBrand(string $title): string
    {
        $brand = ' | '.config('aipolicytracker.site_name');

        return str_ends_with($title, $brand) || mb_strlen($title.$brand) > self::MAX ? $title : $title.$brand;
    }

    /** A meta description within budget, cut at a word boundary. */
    public static function description(string $text): string
    {
        $text = self::clean($text);
        if (mb_strlen($text) <= self::MAX_DESCRIPTION) {
            return $text;
        }
        $cut = mb_substr($text, 0, self::MAX_DESCRIPTION - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $space ?: mb_strlen($cut)), ' ,;:–—-').'…';
    }

    /**
     * Cut to fit. At a true clause boundary (a colon or a dash) when that keeps
     * most of the budget, otherwise at a word with an ellipsis. A parenthesis or
     * a comma is not a clause boundary: cutting "Department for Work and
     * Pensions (DWP) Algorithm Wrongly Flags…" at the bracket leaves a name and
     * throws away the event.
     */
    public static function shorten(string $text, int $max = self::MAX): string
    {
        $text = self::clean($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        if (mb_strlen($short = self::compact($text)) <= $max) {
            return $short;
        }
        $text = $short;
        $floor = (int) ceil($max * 0.6);
        foreach ([': ', ' – ', ' — ', '; '] as $sep) {
            $pos = mb_strrpos(mb_substr($text, 0, $max + mb_strlen($sep)), $sep);
            if ($pos !== false && $pos >= $floor && $pos <= $max) {
                return rtrim(mb_substr($text, 0, $pos));
            }
        }

        return self::shortenWords($text, $max);
    }

    /** Cut at the last whole word that fits, with an ellipsis. */
    public static function shortenWords(string $text, int $max): string
    {
        $text = self::clean($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        if (mb_strlen($text = self::compact($text)) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $space ?: mb_strlen($cut)), ' ,;:–—-(').'…';
    }

    /**
     * The one abbreviation that loses nothing, in the languages the corpus is
     * written in: "AI", and "IA" in Portuguese, Spanish and French, where it is
     * how the instruments abbreviate themselves (Brazil's plan is the "PBIA").
     * Tried before any word is cut.
     */
    public static function compact(string $text): string
    {
        $text = preg_replace('/\bartificial[\s-]+intelligence\b/iu', 'AI', $text);

        return preg_replace('/\b(intelig[eê]ncia[\s-]+artificial|intelligence[\s-]+artificielle)\b/iu', 'IA', $text);
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** True when $haystack already names $needle, so it need not be repeated. */
    private static function mentions(string $haystack, ?string $needle): bool
    {
        return $needle !== null && $needle !== '' && mb_stripos($haystack, $needle) !== false;
    }

    // -------------------------------------------------------------- entities

    /**
     * "<Short title> (<Jurisdiction>, <Year>): Status, Duties & Dates".
     *
     * Shorter forms are tried in order, and the jurisdiction is the last thing
     * to go: "National Artificial Intelligence Policy 2026-2030" names nothing a
     * searcher can place, and the country is what they typed. When nothing else
     * fits, the name is cut to leave room for it.
     */
    public static function policy(PolicyInstrument $policy): string
    {
        $name = self::clean($policy->short_title ?: $policy->title);
        $where = $policy->jurisdiction?->short_name ?: $policy->jurisdiction?->name;
        $year = ($policy->adopted_on ?? $policy->published_on ?? $policy->in_force_on)?->format('Y');

        // A year already in brackets at the end joins the qualifier rather than
        // standing beside it: "(Jordan, 2022)", not "(2022) (Jordan)".
        if (preg_match('/^(.+?)\s*\(((?:19|20)\d{2})\)$/u', $name, $m)) {
            [$name, $year] = [$m[1], $m[2]];
        }
        $place = $where && ! self::mentions($name, $where) ? $where : null;
        $when = $year && ! str_contains($name, $year) ? $year : null;
        $qualifier = implode(', ', array_filter([$place, $when]));

        foreach (array_unique([$name, self::compact($name)]) as $form) {
            $qualified = $qualifier !== '' ? "{$form} ({$qualifier})" : $form;
            $placed = $place ? "{$form} ({$place})" : $form;
            foreach ([$qualified.': Status, Duties & Dates', $qualified.': Status & Dates', $qualified, $placed] as $candidate) {
                if (mb_strlen($candidate) <= self::MAX) {
                    return $candidate;
                }
            }
        }
        $name = self::compact($name);

        return $place ? self::shortenWords($name, self::MAX - mb_strlen(" ({$place})"))." ({$place})" : self::shorten($name);
    }

    /** "<Country> AI Regulation <Year>: Laws, Strategy & Deadlines" */
    public static function jurisdiction(Jurisdiction $jurisdiction, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $name = self::clean($jurisdiction->short_name ?: $jurisdiction->name);

        return self::fit($name.' AI Regulation '.$year, [': Laws, Strategy & Deadlines', ': Laws & Deadlines']);
    }

    /**
     * "<Policy> <reference>: <what the duty is>" — the citation people search
     * for, first, then as much of the duty as fits. The duty is never dropped to
     * make room for the citation; the citation gives way first.
     */
    public static function obligation(Obligation $obligation): string
    {
        $policy = $obligation->policyInstrument;
        $title = self::clean($obligation->title);
        $short = $policy ? self::clean($policy->short_title ?: $policy->title) : null;
        $ref = self::reference($obligation->source_reference);
        if ($ref !== null && $short !== null && self::mentions($short, $ref)) {
            $ref = null;
        }
        foreach ([trim(($short && mb_strlen($short) <= 28 ? $short : '').' '.($ref ?? '')), $ref ?? '', ''] as $lead) {
            $lead = trim($lead);
            $room = self::MAX - ($lead === '' ? 0 : mb_strlen($lead) + 2);
            if ($lead === '' || $room >= 30) {
                return ($lead === '' ? '' : $lead.': ').self::shortenWords($title, $room);
            }
        }

        return self::shortenWords($title, self::MAX);
    }

    /**
     * "<What changed> (<Jurisdiction>, <Mon Year>)". As with policies, the place
     * outlasts the date and the headline gives way to keep it: a change log entry
     * such as "Policy version 1.0 released" says nothing without it.
     */
    public static function change(ChangeEvent $change): string
    {
        $title = self::clean($change->title);
        $where = $change->jurisdiction?->short_name ?: $change->jurisdiction?->name;
        $place = $where && ! self::mentions($title, $where) ? $where : null;
        $when = $change->occurred_on?->format('M Y');

        foreach (array_filter([$place && $when ? " ({$place}, {$when})" : null, $place ? " ({$place})" : null, $place ? null : ($when ? " ({$when})" : null)]) as $tail) {
            if (mb_strlen($title.$tail) <= self::MAX) {
                return $title.$tail;
            }
        }
        if ($place) {
            return self::shortenWords($title, self::MAX - mb_strlen(" ({$place})"))." ({$place})";
        }

        return self::shortenWords($title, self::MAX);
    }

    public static function control(Control $control): string
    {
        return self::fit($control->title, [': AI Duties, Evidence & Clauses', ': AI Governance Control']);
    }

    /**
     * The AI Incident Database's own headline, without its number. For the
     * incidents that concern sexual imagery or abuse, the headline is not used at
     * all: those pages were ranking for searches that had nothing to do with
     * policy, and a title built from the system and the harm type says what the
     * record is about without naming anyone depicted in it.
     */
    public static function incident(ExternalIncident $incident): string
    {
        return self::incidentCollisions()[$incident->incident_id] ?? self::incidentHeadline($incident);
    }

    /**
     * Incidents whose headlines match for longer than a title can show (three
     * Croatian doctors in one fake-endorsement campaign; two Bulgarian actors on
     * the same day) cannot be told apart by a rule that sees one record. This
     * sees them all: a colliding headline takes its month, and if that still
     * collides it is cut from the end, where the distinguishing name usually is.
     * Kept for a day, keyed to the data, so an import refreshes it.
     *
     * @return array<int,string> incident id => title, colliding records only
     */
    private static function incidentCollisions(): array
    {
        $key = 'page-title:incident-collisions:'.ExternalIncident::count().':'.ExternalIncident::max('updated_at');

        return Cache::remember($key, 86400, function () {
            $groups = ExternalIncident::query()->get()->filter->isIndexable()->groupBy(fn ($i) => self::incidentHeadline($i))->filter(fn ($g) => $g->count() > 1);
            $out = [];
            foreach ($groups as $group) {
                $dated = $group->mapWithKeys(fn ($i) => [$i->incident_id => ($when = $i->occurred_on?->format('M Y'))
                    ? self::shortenWords($i->title, self::MAX - mb_strlen(" ({$when})"))." ({$when})"
                    : self::shortenWords($i->title, self::MAX)]);
                $counts = $dated->countBy();
                foreach ($dated as $id => $title) {
                    $out[$id] = $counts[$title] > 1 ? self::shortenFromEnd($group->firstWhere('incident_id', $id)->title) : $title;
                }
            }

            return $out;
        });
    }

    /** The last whole words that fit, after an ellipsis. */
    private static function shortenFromEnd(string $text, int $max = self::MAX): string
    {
        $text = self::clean($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $tail = mb_substr($text, -($max - 1));
        $space = mb_strpos($tail, ' ');

        return '…'.ltrim($space !== false ? mb_substr($tail, $space + 1) : $tail);
    }

    private static function incidentHeadline(ExternalIncident $incident): string
    {
        $year = $incident->occurred_on?->format('Y');
        if (IncidentSensitivity::isSensitive($incident)) {
            return self::shorten(IncidentSensitivity::neutralHeadline($incident));
        }
        $title = self::clean($incident->title);
        if ($year && ! str_contains($title, $year) && mb_strlen("{$title} ({$year})") <= self::MAX) {
            return "{$title} ({$year})";
        }

        // A headline that has to be cut spends the whole budget on its own words.
        // Many incidents open alike ("Purportedly AI-generated video reportedly…"),
        // and the words that follow separate them far more often than a date does.
        return self::shortenWords($title, self::MAX);
    }

    /**
     * "<Risk>: AI Risk (<Author>, <Year>)". The MIT repository keys each paper
     * as author and year run together ("Hendrycks2023"). Written apart, that is
     * a citation a reader recognises; run together, it was an identifier.
     */
    public static function risk(ExternalRisk $risk): string
    {
        $name = self::riskName($risk);
        // A paper can use one name at two levels ("Privacy leakage" as both a
        // category and a subcategory), and the same author can publish the same
        // name in two years. The level and the citation are what tell them apart,
        // so the citation is kept and the name gives way.
        $kind = $risk->level === 'Risk Category' ? 'AI Risk Category' : 'AI Risk';
        $cite = self::citation($risk->quick_ref);
        if ($cite === null) {
            return self::fit($name, [": {$kind}"]);
        }
        foreach ([": {$kind} ({$cite})", " ({$cite})"] as $tail) {
            if (mb_strlen($name.$tail) <= self::MAX) {
                return $name.$tail;
            }
        }

        return self::shortenWords($name, self::MAX - mb_strlen(" ({$cite})"))." ({$cite})";
    }

    /**
     * The name a reader would search for. Many entries are written "<shared
     * parent> - <specific risk>", and cutting to length would keep the parent
     * and lose the only words that tell siblings apart, so the specific part
     * leads. Some entries carry only a placeholder ("-") for a name; those are
     * named from the start of their description instead.
     */
    public static function riskName(ExternalRisk $risk): string
    {
        $named = fn (?string $s) => $s !== null && preg_match('/\p{L}/u', $s) ? self::clean($s) : null;
        $name = $named($risk->risk_subcategory) ?? $named($risk->risk_category);
        if ($name === null) {
            return $named($risk->description) ? self::shortenWords($risk->description, 44) : 'Unnamed risk entry';
        }
        // "<parent> - <specific>", "<parent>: <specific>", "<parent> (<specific>)"
        foreach (['/^(.{6,}?)\s+[-–—]\s+(.{3,})$/u', '/^(.{6,}?):\s+(.{3,})$/u', '/^(.{6,}?)\s+\(([^()]{3,})\)$/u'] as $shape) {
            if (preg_match($shape, $name, $m) && ! preg_match('/^(general|other|misc\w*)$/i', trim($m[2]))) {
                return trim($m[2]).': '.trim($m[1]);
            }
        }

        return $name;
    }

    /** "Hendrycks2023" → "Hendrycks, 2023"; "Gabriel2024a" → "Gabriel, 2024a". */
    public static function citation(?string $quickRef): ?string
    {
        if (! $quickRef) {
            return null;
        }
        if (preg_match('/^(.*?\D)((?:19|20)\d{2}[a-z]?)$/u', trim($quickRef), $m)) {
            return trim($m[1], ' _-').', '.$m[2];
        }

        return self::leaksIdentifier($quickRef) ? null : $quickRef;
    }

    /** The first clause of a source reference, when it is short enough to lead a title. */
    private static function reference(?string $ref): ?string
    {
        if (! $ref) {
            return null;
        }
        $first = self::clean(preg_split('/[;,]/', $ref)[0]);

        return mb_strlen($first) <= 22 ? $first : null;
    }
}
