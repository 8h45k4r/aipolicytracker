<?php

namespace App\Console\Commands;

use App\Models\PolicyInstrument;
use App\Services\Report\StateOfAiRegulation;
use Illuminate\Console\Command;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Draws the quarter's random sample of verified policy records for independent
 * second checks.
 *
 * The draw is reproducible: the same seed over the same population always returns the
 * same records, so anyone can re-run it from the repository and confirm the sample was
 * not hand-picked. The seed defaults to the quarter's label (2026-Q4), so the default
 * draw for a quarter is fixed before anyone sees which records it picks. The
 * population is sorted by slug before shuffling, so database order cannot change it.
 */
class VerificationSampleCommand extends Command
{
    protected $signature = 'verification:sample
        {--percent= : Share of verified records to sample (default: config verification.independent_checks.sample_percent)}
        {--seed= : Seed for the draw; defaults to the quarter label}
        {--quarter= : Quarter the sample is for, such as 2026-Q4 (default: the current quarter)}
        {--include-checked : Include records that already carry a second check}';

    protected $description = 'Print a reproducible random sample of verified policy records for independent second checks';

    public function handle(): int
    {
        $quarter = (string) ($this->option('quarter') ?: StateOfAiRegulation::currentQuarter());
        if (! StateOfAiRegulation::isValidQuarter($quarter)) {
            $this->error("Not a quarter: {$quarter}");

            return self::FAILURE;
        }
        $percent = $this->option('percent') ?? config('verification.independent_checks.sample_percent', 20);
        if (! is_numeric($percent) || (float) $percent <= 0 || (float) $percent > 100) {
            $this->error('--percent must be a number greater than 0 and at most 100.');

            return self::FAILURE;
        }
        $seedLabel = (string) ($this->option('seed') ?? $quarter);

        $population = PolicyInstrument::published()
            ->where('review_status', 'verified')
            ->whereNotNull('reviewed_by')
            ->orderBy('slug')
            ->get(['slug', 'second_review', 'reviewed_by'])
            ->when(! $this->option('include-checked'), fn ($c) => $c->reject(fn (PolicyInstrument $p) => $p->secondReview() !== null))
            ->pluck('slug')
            ->sort(SORT_STRING)
            ->values()
            ->all();

        $sample = self::draw($population, (float) $percent, $seedLabel);

        $this->line("# Independent-check sample for {$quarter}");
        $this->line('# seed: '.$seedLabel.' · population: '.count($population).' verified records · sample: '.count($sample).' ('.rtrim(rtrim(number_format((float) $percent, 2, '.', ''), '0'), '.').'%)');
        foreach ($sample as $slug) {
            $this->line($slug);
        }

        return self::SUCCESS;
    }

    /**
     * The sample itself: ceil(percent of the population), drawn with a seeded
     * Mersenne Twister and returned sorted. Deterministic for a given population,
     * percent and seed, on any machine.
     *
     * @param  list<string>  $population
     * @return list<string>
     */
    public static function draw(array $population, float $percent, string $seed): array
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
