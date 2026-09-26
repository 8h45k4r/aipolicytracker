<?php

namespace App\Services\Hubs;

use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Which jurisdictions have a hub address, which regions have one, when a hub
 * may be indexed, and the canonical form of a comparison pair. Everything
 * here reads config/hubs.php and the records; nothing is content.
 */
final class HubCatalog
{
    public const PREFIX = 'ai-regulation-';

    /** @return list<string> jurisdiction slugs with a hub */
    public static function countries(): array
    {
        return array_values(config('hubs.countries', []));
    }

    /** @return array<string,string> hub slug => region name */
    public static function regions(): array
    {
        return config('hubs.regions', []);
    }

    public static function hasHub(string $jurisdictionSlug): bool
    {
        return in_array($jurisdictionSlug, self::countries(), true);
    }

    /** The hub slug for a country with one, e.g. "ai-regulation-japan". */
    public static function hubSlug(string $jurisdictionSlug): ?string
    {
        return self::hasHub($jurisdictionSlug) ? self::PREFIX.$jurisdictionSlug : null;
    }

    /** The route constraint: every country and region hub, alternated. */
    public static function pattern(): string
    {
        $slugs = array_merge(array_map(fn ($c) => self::PREFIX.$c, self::countries()), array_map(fn ($r) => self::PREFIX.$r, array_keys(self::regions())));

        return implode('|', array_map('preg_quote', $slugs));
    }

    /** The country slug a hub address names, or null when it names a region. */
    public static function countryFor(string $hub): ?string
    {
        $slug = substr($hub, strlen(self::PREFIX));

        return self::hasHub($slug) ? $slug : null;
    }

    /** The region name a hub address names, or null when it names a country. */
    public static function regionFor(string $hub): ?string
    {
        return self::regions()[substr($hub, strlen(self::PREFIX))] ?? null;
    }

    public static function regionUrl(string $regionSlug): string
    {
        return route('hubs.show', self::PREFIX.$regionSlug);
    }

    public static function regionSlugFor(?string $regionName): ?string
    {
        return $regionName ? (array_search($regionName, self::regions(), true) ?: null) : null;
    }

    /**
     * The thin guard: a hub is indexed with at least `min_sourced_instruments`
     * published instruments carrying an official source, or one verified strategy.
     */
    public static function isHubIndexable(Jurisdiction $jurisdiction): bool
    {
        if (! $jurisdiction->isIndexable()) {
            return false;
        }
        $q = PolicyInstrument::published()->where('jurisdiction_id', $jurisdiction->id);
        $sourced = (clone $q)->whereNotNull('official_source_url')->count();
        if ($sourced >= (int) config('hubs.min_sourced_instruments', 2)) {
            return true;
        }

        return (clone $q)->where('instrument_type', 'strategy')->where('review_status', 'verified')->exists();
    }

    /**
     * The dated events on a jurisdiction's record, oldest first: an instrument's
     * publication, adoption, entry into force and application, and its deadlines.
     *
     * @param  Collection<int,PolicyInstrument>  $policies  with deadlines loaded
     * @return list<array{date:CarbonInterface, label:string, policy:PolicyInstrument, kind:string, future:bool}>
     */
    public static function timeline(Collection $policies): array
    {
        $events = [];
        foreach ($policies as $p) {
            $name = $p->short_title ?: $p->title;
            foreach (['published_on' => 'published', 'adopted_on' => 'adopted', 'in_force_on' => 'entered into force', 'applies_from' => 'applies from this date'] as $field => $label) {
                if ($p->{$field}) {
                    $events[] = ['date' => $p->{$field}, 'label' => $name.' '.$label, 'policy' => $p, 'kind' => $field, 'future' => $p->{$field}->isFuture()];
                }
            }
            foreach ($p->deadlines as $d) {
                if ($d->due_on && in_array($d->deadline_status, ['scheduled', 'passed'], true)) {
                    $events[] = ['date' => $d->due_on, 'label' => $d->title.' ('.$name.')', 'policy' => $p, 'kind' => 'deadline', 'future' => $d->due_on->isFuture()];
                }
            }
        }
        usort($events, fn ($a, $b) => [$a['date']->timestamp, $a['label']] <=> [$b['date']->timestamp, $b['label']]);
        // The same date and label can come from an in-force date that is also recorded as a deadline.
        $seen = [];

        return array_values(array_filter($events, function ($e) use (&$seen) {
            $key = $e['date']->toDateString().'|'.mb_strtolower($e['label']);
            if (isset($seen[$key])) {
                return false;
            }

            return $seen[$key] = true;
        }));
    }

    // ---------------------------------------------------------- comparisons

    /** Canonical pair slug: alphabetical by jurisdiction slug, so a pair has one address. */
    public static function pairSlug(string $a, string $b): string
    {
        [$x, $y] = strcmp($a, $b) <= 0 ? [$a, $b] : [$b, $a];

        return $x.'-vs-'.$y;
    }

    /** @return array{0:string,1:string}|null the two slugs a pair address names, in the order given */
    public static function parsePair(string $slug): ?array
    {
        if (substr_count($slug, '-vs-') !== 1) {
            return null;
        }
        [$a, $b] = explode('-vs-', $slug, 2);

        return $a !== '' && $b !== '' && $a !== $b ? [$a, $b] : null;
    }

    /** @return list<string> canonical pair slugs that are indexed and listed */
    public static function indexablePairs(): array
    {
        $pairs = array_map(fn ($p) => self::pairSlug($p[0], $p[1]), config('hubs.compare_pairs', []));

        return array_values(array_unique($pairs));
    }

    public static function isPairIndexable(string $pairSlug): bool
    {
        return in_array($pairSlug, self::indexablePairs(), true);
    }

    /** The curated comparison that covers a pair, if one exists (its address stays canonical). */
    public static function curatedFor(string $a, string $b): ?string
    {
        foreach (config('content.comparisons', []) as $slug => $c) {
            $js = $c['jurisdictions'] ?? [];
            if (count($js) >= 2 && in_array($a, $js, true) && in_array($b, $js, true) && $js[0] === $a && $js[1] === $b) {
                return $slug;
            }
        }
        foreach (config('content.comparisons', []) as $slug => $c) {
            $js = $c['jurisdictions'] ?? [];
            if (count($js) === 2 && in_array($a, $js, true) && in_array($b, $js, true)) {
                return $slug;
            }
        }

        return null;
    }
}
