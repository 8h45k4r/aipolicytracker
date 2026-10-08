<?php

namespace App\Console\Commands;

use App\Services\Verification\VerificationSample;
use Illuminate\Console\Command;

/**
 * Prints the quarter's random sample of verified policy records for independent
 * second checks. The draw and its rules are in VerificationSample, which the admin's
 * Independent checks page also uses, so the two never disagree. Changes nothing.
 */
class VerificationSampleCommand extends Command
{
    protected $signature = 'verification:sample
        {--percent= : Share of verified records to sample (default: config verification.independent_checks.sample_percent)}
        {--seed= : Seed for the draw; defaults to the quarter label}
        {--quarter= : Quarter the sample is for, such as 2026-Q4 (default: the current quarter)}
        {--include-checked : Include records that already carry a second check}';

    protected $description = 'Print a reproducible random sample of verified policy records for independent second checks';

    public function handle(VerificationSample $sampler): int
    {
        try {
            $sample = $sampler->draw($this->option('quarter') ?: null, $this->option('percent'), $this->option('seed'), (bool) $this->option('include-checked'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("# Independent-check sample for {$sample['quarter']}");
        $this->line('# seed: '.$sample['seed'].' · population: '.$sample['population'].' verified records · sample: '.count($sample['slugs']).' ('.rtrim(rtrim(number_format($sample['percent'], 2, '.', ''), '0'), '.').'%)');
        foreach ($sample['slugs'] as $slug) {
            $this->line($slug);
        }

        return self::SUCCESS;
    }

    /**
     * Kept for callers of the command class; the draw lives in VerificationSample.
     *
     * @param  list<string>  $population
     * @return list<string>
     */
    public static function draw(array $population, float $percent, string $seed): array
    {
        return VerificationSample::pick($population, $percent, $seed);
    }
}
