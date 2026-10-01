<?php

namespace App\Console\Commands;

use App\Services\Seo\IndexNow;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Submit recently changed pages (or the given URLs) to IndexNow by hand. */
class SeoIndexNowCommand extends Command
{
    protected $signature = 'seo:indexnow {--since=24 hours : Submit records changed within this period} {--url=* : Submit these URLs instead} {--dry-run : List the URLs without sending}';

    protected $description = 'Tell IndexNow search engines which pages changed';

    public function handle(IndexNow $indexNow): int
    {
        $urls = $this->option('url') ?: $indexNow->changedSince(Carbon::now()->sub(\DateInterval::createFromDateString((string) $this->option('since'))));
        if ($urls === []) {
            $this->info('Nothing changed in that period.');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            foreach ($urls as $url) {
                $this->line($url);
            }

            return self::SUCCESS;
        }
        if (! $indexNow->enabled()) {
            $this->warn('INDEXNOW_KEY is not set (or not 8 to 128 letters, digits or dashes); nothing sent.');

            return self::SUCCESS;
        }
        $this->info('Submitted '.$indexNow->submit($urls).' of '.count($urls).' URLs.');

        return self::SUCCESS;
    }
}
