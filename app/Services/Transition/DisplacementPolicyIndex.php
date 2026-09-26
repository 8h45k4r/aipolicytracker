<?php

namespace App\Services\Transition;

use App\Models\DisplacementIndexSnapshot;
use App\Models\Jurisdiction;
use App\Models\TransitionMeasure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The displacement policy index: a quarterly 0–100 score per jurisdiction,
 * the sum of four 0–25 sub-scores (disclosure, safety net, transition
 * funding, worker voice). Each sub-score is the strongest verified measure
 * in that dimension, weighted by how far it has got: in force or enacted
 * 25, pilot 18, passed a chamber 12, introduced or proposed 8. Drafts and
 * unverified records score nothing. The function is pure and versioned; a
 * change to the weights is a new version, never a silent re-score.
 */
final class DisplacementPolicyIndex
{
    public const VERSION = '1.0';

    public const DIMENSIONS = ['disclosure' => 'Disclosure', 'safety_net' => 'Safety net', 'transition_funding' => 'Transition funding', 'worker_voice' => 'Worker voice'];

    public const WEIGHTS = ['in_force' => 25, 'enacted' => 25, 'pilot' => 18, 'passed_chamber' => 12, 'in_committee' => 8, 'introduced' => 8, 'proposed' => 8];

    /**
     * Pure: measures in, score out. Only measures that are published, verified
     * and dated on or before the quarter's end count (an undated verified
     * measure counts from the quarter it was verified).
     *
     * @param  iterable<TransitionMeasure|array>  $measures
     * @return array{score:int, subscores:array<string,int>, inputs:list<array{slug:string, dimension:string, status:string, weight:int}>, version:string, quarter:string}
     */
    public static function score(iterable $measures, string $quarter): array
    {
        $end = self::quarterEnd($quarter);
        $sub = array_fill_keys(array_keys(self::DIMENSIONS), 0);
        $inputs = [];
        foreach ($measures as $m) {
            $m = is_array($m) ? $m : $m->toArray() + ['published_at' => $m->published_at];
            if (($m['review_status'] ?? null) !== 'verified' || empty($m['published_at'])) {
                continue;
            }
            $dimension = TransitionMeasure::DIMENSION[$m['measure_type']] ?? null;
            $weight = self::WEIGHTS[$m['status']] ?? 0;
            if (! $dimension || $weight === 0) {
                continue;
            }
            $dated = $m['in_force_on'] ?? $m['enacted_on'] ?? $m['introduced_on'] ?? $m['last_verified_at'] ?? null;
            if ($dated && CarbonImmutable::parse($dated)->greaterThan($end)) {
                continue;
            }
            $inputs[] = ['slug' => $m['slug'], 'dimension' => $dimension, 'status' => $m['status'], 'weight' => $weight];
            $sub[$dimension] = max($sub[$dimension], $weight);
        }

        return ['score' => (int) array_sum($sub), 'subscores' => $sub, 'inputs' => $inputs, 'version' => self::VERSION, 'quarter' => $quarter];
    }

    public static function currentQuarter(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return $now->format('Y').'-Q'.$now->quarter;
    }

    public static function quarterEnd(string $quarter): CarbonImmutable
    {
        [$year, $q] = explode('-Q', $quarter);

        return CarbonImmutable::create((int) $year, (int) $q * 3, 1)->endOfMonth()->endOfDay();
    }

    /**
     * Compute and store the snapshot for every jurisdiction with a published
     * measure, for the given quarter. Idempotent per (jurisdiction, quarter,
     * version): a re-run replaces the row.
     *
     * @return Collection<int, DisplacementIndexSnapshot>
     */
    public static function compute(?string $quarter = null): Collection
    {
        $quarter ??= self::currentQuarter();
        $out = collect();
        $byJurisdiction = TransitionMeasure::whereNotNull('published_at')->get()->groupBy('jurisdiction_id');
        foreach ($byJurisdiction as $jurisdictionId => $measures) {
            $result = self::score($measures, $quarter);
            // Nothing verified means no score, not a zero: a snapshot would dress a gap as a fact.
            if ($result['inputs'] === []) {
                DisplacementIndexSnapshot::where('jurisdiction_id', $jurisdictionId)->where('quarter', $quarter)->where('version', self::VERSION)->delete();

                continue;
            }
            $out->push(DisplacementIndexSnapshot::updateOrCreate(
                ['jurisdiction_id' => $jurisdictionId, 'quarter' => $quarter, 'version' => self::VERSION],
                ['score' => $result['score'], 'subscores' => $result['subscores'], 'inputs' => $result['inputs'], 'computed_at' => now()]
            ));
        }

        return $out;
    }

    /** @return Collection<int, DisplacementIndexSnapshot> latest snapshot per jurisdiction, highest first */
    public static function latest(): Collection
    {
        return DisplacementIndexSnapshot::with('jurisdiction')->where('version', self::VERSION)->orderByDesc('quarter')->get()
            ->unique('jurisdiction_id')->sortByDesc('score')->values();
    }

    public static function explain(): array
    {
        return [
            'version' => self::VERSION,
            'dimensions' => self::DIMENSIONS,
            'weights' => self::WEIGHTS,
            'rule' => 'Each dimension scores the strongest published, verified measure recorded for the jurisdiction in that dimension, by status weight; the four dimensions sum to the index. Drafts, unverified records and measures dated after the quarter do not count.',
            'types_by_dimension' => collect(TransitionMeasure::DIMENSION)->groupBy(fn ($d) => $d)->map(fn ($g) => $g->keys()->all())->all(),
        ];
    }

    public static function jurisdictionName(int $id): string
    {
        return Jurisdiction::whereKey($id)->value('name') ?? (string) $id;
    }
}
