<?php

namespace App\Console\Commands;

use App\Services\Report\StateOfAiRegulation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** Freezes the previous quarter's state-of-AI-regulation figures (idempotent). Runs on the first day of each quarter. */
class FreezeReportCommand extends Command
{
    protected $signature = 'report:freeze {--quarter= : A quarter such as 2026-Q3; default is the quarter that just closed}';

    protected $description = 'Freeze a quarter of the state-of-AI-regulation report';

    public function handle(): int
    {
        $quarter = $this->option('quarter') ?: StateOfAiRegulation::currentQuarter(CarbonImmutable::now()->subMonths(3));
        if (! StateOfAiRegulation::isValidQuarter($quarter)) {
            $this->error("Not a quarter: {$quarter}");

            return self::FAILURE;
        }
        $snapshot = StateOfAiRegulation::freeze($quarter);
        $this->info("Frozen {$quarter} (v".StateOfAiRegulation::VERSION."): {$snapshot->data['totals']['instruments']} instruments, {$snapshot->data['totals']['jurisdictions_with_binding_law']} jurisdictions with binding law.");

        return self::SUCCESS;
    }
}
