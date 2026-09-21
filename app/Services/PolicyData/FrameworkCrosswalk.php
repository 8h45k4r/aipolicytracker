<?php

namespace App\Services\PolicyData;

use App\Models\FrameworkMapping;
use App\Models\Jurisdiction;
use Illuminate\Support\Collection;

/**
 * Reads the framework_mappings table as a browseable crosswalk between legal duties and
 * the standards organisations are audited against.
 *
 * A mapping is an editorial judgement recorded against one obligation: it cites a clause
 * or function number and never reproduces standard text. Every figure this service returns
 * is a count of those recorded mappings, so pages built on it can state their own denominator
 * instead of implying the crosswalk is complete.
 */
class FrameworkCrosswalk
{
    /** A framework page needs this many mapped duties before it is worth indexing. */
    public const MIN_INDEXABLE_OBLIGATIONS = 5;

    /** A single law-to-standard crosswalk needs this many rows before it is worth indexing. */
    public const MIN_INDEXABLE_ROWS = 8;

    /** Bucket for references that name no clause or function, so no mapping is silently dropped. */
    public const OTHER_FAMILY = 'Other references';

    /** @return Collection<int, array<string, mixed>> Every framework with a mapping, most covered first. */
    public function summary(): Collection
    {
        $grouped = $this->mappings()->groupBy('framework');

        return collect(config('frameworks'))
            ->map(function (array $meta, string $key) use ($grouped) {
                $rows = $grouped->get($key, collect());

                return $meta + [
                    'key' => $key,
                    'obligations' => $rows->pluck('obligation_id')->unique()->count(),
                    'mappings' => $rows->count(),
                    'jurisdictions' => $rows->pluck('obligation.policyInstrument.jurisdiction.id')->unique()->count(),
                    'instruments' => $rows->pluck('obligation.policy_instrument_id')->unique()->count(),
                    'binding' => $rows->filter(fn ($m) => $m->obligation->is_binding)->pluck('obligation_id')->unique()->count(),
                ];
            })
            ->sortByDesc('obligations')
            ->values();
    }

    /** One framework: its mappings grouped by clause or function, plus a jurisdiction breakdown. */
    public function framework(string $key): array
    {
        $rows = $this->mappings()->where('framework', $key);

        $families = collect();
        foreach ($rows as $mapping) {
            foreach ($mapping->families() ?: [self::OTHER_FAMILY] as $family) {
                $families->push(['family' => $family, 'mapping' => $mapping]);
            }
        }

        return [
            'meta' => config('frameworks.'.$key),
            'key' => $key,
            'mappings' => $rows->count(),
            'obligations' => $rows->pluck('obligation_id')->unique()->count(),
            'families' => $families->groupBy('family')
                ->map(fn (Collection $g) => $g->pluck('mapping')->sortBy(fn ($m) => $m->obligation->title)->values())
                ->sortKeysUsing($this->familyOrder($key)),
            'jurisdictions' => $this->jurisdictionsFor($key),
            'indexable' => $rows->pluck('obligation_id')->unique()->count() >= self::MIN_INDEXABLE_OBLIGATIONS,
        ];
    }

    /** One law-to-standard crosswalk: every duty in a jurisdiction that maps to this framework. */
    public function crosswalk(string $key, Jurisdiction $jurisdiction): array
    {
        $rows = $this->mappings()
            ->where('framework', $key)
            ->filter(fn ($m) => $m->obligation->policyInstrument->jurisdiction_id === $jurisdiction->id)
            ->sortBy([fn ($a, $b) => strcmp($a->obligation->policyInstrument->title, $b->obligation->policyInstrument->title), fn ($a, $b) => $a->obligation->sort_order <=> $b->obligation->sort_order])
            ->values();

        return [
            'meta' => config('frameworks.'.$key),
            'key' => $key,
            'jurisdiction' => $jurisdiction,
            'rows' => $rows,
            'instruments' => $rows->pluck('obligation.policyInstrument')->unique('id')->values(),
            'total_obligations' => $jurisdiction->policyInstruments()->published()->withCount(['obligations' => fn ($q) => $q->published()])->get()->sum('obligations_count'),
            'indexable' => $rows->count() >= self::MIN_INDEXABLE_ROWS,
        ];
    }

    /** @return Collection<int, array<string, mixed>> Jurisdictions with mappings to this framework. */
    public function jurisdictionsFor(string $key): Collection
    {
        return $this->mappings()->where('framework', $key)
            ->groupBy('obligation.policyInstrument.jurisdiction.slug')
            ->map(fn (Collection $g) => [
                'jurisdiction' => $g->first()->obligation->policyInstrument->jurisdiction,
                'rows' => $g->count(),
                'instruments' => $g->pluck('obligation.policy_instrument_id')->unique()->count(),
            ])
            ->sortByDesc('rows')
            ->values();
    }

    /** Resolves a URL slug ("iso-42001") to a framework key ("iso_42001"). */
    public function keyForSlug(string $slug): ?string
    {
        foreach (config('frameworks') as $key => $meta) {
            if ($meta['slug'] === $slug) {
                return $key;
            }
        }

        return null;
    }

    /** Every mapping on a published obligation, with the chain each page needs. */
    protected function mappings(): Collection
    {
        return once(fn () => FrameworkMapping::with(['obligation.policyInstrument.jurisdiction'])
            ->get()
            ->filter(fn ($m) => $m->obligation?->published_at
                && $m->obligation->policyInstrument?->published_at
                && $m->obligation->policyInstrument->jurisdiction?->published_at)
            ->values());
    }

    /** NIST functions read in lifecycle order; clause numbers read numerically; the bucket sorts last. */
    protected function familyOrder(string $key): callable
    {
        $nist = ['GOVERN' => 1, 'MAP' => 2, 'MEASURE' => 3, 'MANAGE' => 4];

        return function (string $a, string $b) use ($key, $nist) {
            $rank = function (string $f) use ($key, $nist) {
                if ($f === self::OTHER_FAMILY) {
                    return 9999;
                }
                if ($key === 'nist_ai_rmf') {
                    return $nist[$f] ?? 998;
                }

                return preg_match('/^Clause (\d+)/', $f, $m) ? (int) $m[1] : 900;
            };

            return [$rank($a), $a] <=> [$rank($b), $b];
        };
    }
}
