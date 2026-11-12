<?php

namespace App\Console\Commands;

use App\Services\News\NewsCollector;
use Illuminate\Console\Command;

/** Collects AI headlines from the official feeds in config/news.php. */
class SyncNewsCommand extends Command
{
    protected $signature = 'news:sync {--source= : Only this source key}';

    protected $description = 'Collect AI policy headlines from official sources\' own feeds';

    public function handle(NewsCollector $collector): int
    {
        if (! config('news.enabled')) {
            $this->info('News collection is switched off (NEWS_SYNC).');

            return self::SUCCESS;
        }
        $report = $collector->sync($this->option('source') ?: null);
        $failed = 0;
        foreach ($report as $key => $r) {
            $this->line(sprintf('%-22s %s', $key, $r['error'] ? 'FAILED: '.$r['error'] : "{$r['fetched']} fetched, {$r['kept']} about AI, {$r['created']} new"));
            $failed += $r['error'] ? 1 : 0;
        }

        // One feed down is reported, not a failed run; every feed down is.
        return $failed > 0 && $failed === count($report) ? self::FAILURE : self::SUCCESS;
    }
}
