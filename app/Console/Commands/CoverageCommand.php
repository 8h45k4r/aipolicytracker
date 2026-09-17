<?php

namespace App\Console\Commands;

use App\Services\Completeness\CompletenessReport;
use Illuminate\Console\Command;

/**
 * Reports the corpus against the completeness policy and fails when required
 * gaps exceed the budget. Run in CI on every data change so a record cannot be
 * published without the things that make it checkable.
 */
class CoverageCommand extends Command
{
    protected $signature = 'policy:coverage {--json : Machine-readable summary} {--list=0 : How many gaps to print} {--kind= : Limit the list to one record kind} {--check= : Limit the list to one check}';

    protected $description = 'Check published records against the completeness policy and list what is missing';

    public function handle(CompletenessReport $completeness): int
    {
        $report = $completeness->report();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'records' => $report['records'], 'complete' => $report['complete'], 'incomplete' => $report['incomplete'],
                'required_gaps' => $report['required_gaps'], 'expected_gaps' => $report['expected_gaps'],
                'budget' => $report['budget'], 'pass' => $report['pass'],
                'checks' => $report['checks'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['pass'] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(['Check', 'Kind', 'Severity', 'Missing', 'Applies to'], array_map(fn ($c) => [
            $c['label'], $c['kind'], $c['severity'], $c['missing'], $c['applicable'],
        ], $report['checks']));

        $limit = max(0, (int) $this->option('list'));
        if ($limit) {
            $queue = $completeness->queue($this->option('kind') ?: null, $this->option('check') ?: null);
            if ($queue->isNotEmpty()) {
                $this->newLine();
                $this->line('Queue, required first:');
                foreach ($queue->take($limit) as $gap) {
                    $this->line(sprintf('  [%s] %s — %s (%s)', $gap['kind'], $gap['record']->slug, $gap['check']['label'], $gap['check']['severity']));
                }
            }
        }

        $this->newLine();
        $this->line(sprintf('%d published records, %d complete, %d with at least one gap.', $report['records'], $report['complete'], $report['incomplete']));
        $this->line(sprintf('Required gaps: %d (budget %d). Expected gaps: %d.', $report['required_gaps'], $report['budget'], $report['expected_gaps']));

        if (! $report['pass']) {
            $this->error('Completeness policy failed: required gaps exceed the budget. Fill them, or state in the pull request why the budget must change.');

            return self::FAILURE;
        }
        if ($report['budget'] > 0) {
            $this->warn(sprintf('Budget is %d. It is a ratchet: after closing a batch, lower it to the new count in config/completeness.php.', $report['budget']));
        }
        $this->info('Completeness policy passed.');

        return self::SUCCESS;
    }
}
