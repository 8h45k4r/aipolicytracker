<?php

namespace App\Console\Commands;

use App\Services\Verification\VerificationPolicy;
use Illuminate\Console\Command;

/**
 * Reports the corpus against the verification policy and fails when critical
 * breaches exceed the budget. Run in CI on every data change so the site
 * cannot go stale without someone deciding to let it.
 */
class VerificationFreshnessCommand extends Command
{
    protected $signature = 'policy:freshness {--json : Machine-readable summary} {--list=20 : How many overdue records to print}';

    protected $description = 'Check published records against the verification policy and list what is overdue';

    public function handle(VerificationPolicy $policy): int
    {
        $report = $policy->report();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'covered' => $report['covered'], 'overdue' => $report['overdue'],
                'critical_overdue' => $report['critical_overdue'], 'never_verified' => $report['never'],
                'budget' => $report['budget'], 'pass' => $report['pass'],
                'rules' => $report['rules'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['pass'] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(['Rule', 'Track', 'Max age', 'Critical', 'Covered', 'Overdue', 'Never checked'], array_map(fn ($r) => [
            $r['label'], $r['track'], $r['days'].' days', $r['critical'] ? 'yes' : 'no', $r['covered'], $r['overdue'], $r['never'],
        ], $report['rules']));

        $limit = max(0, (int) $this->option('list'));
        if ($limit && $report['stale']->isNotEmpty()) {
            $this->newLine();
            $this->line('Longest overdue:');
            foreach ($report['stale']->take($limit) as $row) {
                $age = $row['never'] ? 'never checked' : $row['age'].' days';
                $this->line(sprintf('  [%s] %s — %s (limit %d)', $row['kind'], $row['record']->slug, $age, $row['rule']['days']));
            }
        }

        $this->newLine();
        $this->line(sprintf('%d records under the policy, %d overdue, %d never checked.', $report['covered'], $report['overdue'], $report['never']));
        $this->line(sprintf('Critical breaches: %d (budget %d).', $report['critical_overdue'], $report['budget']));

        if (! $report['pass']) {
            $this->error('Verification policy failed: critical breaches exceed the budget. Verify the records above, or state in the pull request why the budget must change.');

            return self::FAILURE;
        }
        if ($report['budget'] > 0) {
            $this->warn(sprintf('Budget is %d. It is a ratchet: after verifying a batch, lower it to the new count in config/verification.php.', $report['budget']));
        }
        $this->info('Verification policy passed.');

        return self::SUCCESS;
    }
}
