<?php

namespace App\Services\Verification;

use App\Models\PolicyInstrument;
use App\Services\Report\StateOfAiRegulation;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The quarter's random sample of verified policy records for independent second checks.
 * `verification:sample` prints it and the admin's Independent checks page lists it, so
 * the two always show the same records.
 *
 * The draw is reproducible: the same seed over the same population always returns the
 * same records, so anyone can re-run it from the repository and confirm the sample was
 * not hand-picked. The seed defaults to the quarter's label (2026-Q4), so the default
 * draw for a quarter is fixed before anyone sees which records it picks. The
 * population is sorted by slug before shuffling, so database order cannot change it.
 *
 * The population is published, verified records with a named first reviewer that do
 * not yet carry a second check. A record whose second check names this quarter's sample
 * (`second_review.sample`) stays in it: otherwise recording a check would shrink the
 * population, and re-running the draw later in the quarter would pick different records.
 */
class VerificationSample
{
    /**
     * @return array{quarter: string, seed: string, percent: float, population: int, slugs: list<string>}
     *
     * @throws \InvalidArgumentException on a malformed quarter or a percent outside (0, 100]
     */
    public function draw(?string $quarter = null, float|int|string|null $percent = null, ?string $seed = null, bool $includeChecked = false): array
    {
        $quarter = (string) ($quarter ?: StateOfAiRegulation::currentQuarter());
        if (! StateOfAiRegulation::isValidQuarter($quarter)) {
            throw new \InvalidArgumentException("Not a quarter: {$quarter}");
        }
        $percent ??= config('verification.independent_checks.sample_percent', 20);
        if (! is_numeric($percent) || (float) $percent <= 0 || (float) $percent > 100) {
            throw new \InvalidArgumentException('--percent must be a number greater than 0 and at most 100.');
        }
        $seed = (string) ($seed ?? $quarter);
        $population = $this->population($quarter, $includeChecked);

        return [
            'quarter' => $quarter,
            'seed' => $seed,
            'percent' => (float) $percent,
            'population' => count($population),
            'slugs' => self::pick($population, (float) $percent, $seed),
        ];
    }

    /** @return list<string> slugs, sorted */
    public function population(string $quarter, bool $includeChecked = false): array
    {
        return PolicyInstrument::published()
            ->where('review_status', 'verified')
            ->whereNotNull('reviewed_by')
            ->orderBy('slug')
            ->get(['slug', 'second_review', 'reviewed_by'])
            ->when(! $includeChecked, fn ($c) => $c->reject(function (PolicyInstrument $p) use ($quarter) {
                $review = $p->secondReview();

                return $review !== null && (string) ($review['sample'] ?? '') !== $quarter;
            }))
            ->pluck('slug')
            ->sort(SORT_STRING)
            ->values()
            ->all();
    }

    /**
     * The sample itself: ceil(percent of the population), drawn with a seeded
     * Mersenne Twister and returned sorted. Deterministic for a given population,
     * percent and seed, on any machine.
     *
     * @param  list<string>  $population
     * @return list<string>
     */
    public static function pick(array $population, float $percent, string $seed): array
    {
        sort($population, SORT_STRING);
        $size = (int) min(count($population), ceil(count($population) * $percent / 100));
        if ($size === 0) {
            return [];
        }
        $seedInt = is_numeric($seed) && (string) (int) $seed === $seed ? (int) $seed : crc32($seed);
        $randomizer = new Randomizer(new Mt19937($seedInt));
        $picked = array_slice($randomizer->shuffleArray($population), 0, $size);
        sort($picked, SORT_STRING);

        return $picked;
    }
}
