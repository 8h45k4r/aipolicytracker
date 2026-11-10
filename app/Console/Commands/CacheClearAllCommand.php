<?php

namespace App\Console\Commands;

use App\Support\ContentCache;
use Illuminate\Console\Command;

/**
 * Clears what a deploy rebuilds (config, routes, views, events) and the cached public page
 * content, then optionally rebuilds the production caches. Used by the maintenance workflow
 * (deploy/remote-maintenance.sh).
 *
 * It deliberately does not run cache:clear. The cache store is the database, and it holds
 * state that is not a cache: the scheduler's last tick (aip:health fails without it), the
 * AI Incident Database sync cursor, job locks and rate-limit counters. Stale page content is
 * dropped by moving ContentCache to its next generation instead.
 *
 * Every step's exit code is checked; the command fails if any step does.
 */
class CacheClearAllCommand extends Command
{
    protected $signature = 'aip:cache-clear {--rebuild : Rebuild the config, route and view caches afterwards}';

    protected $description = 'Clear compiled caches and cached page content (not the cache store), optionally rebuilding';

    public function handle(): int
    {
        $failed = [];
        $steps = ['config:clear' => 'Config cache', 'route:clear' => 'Route cache', 'view:clear' => 'Compiled views', 'event:clear' => 'Event cache'];
        foreach ($steps as $command => $label) {
            $this->step($command, $label, 'cleared', $failed);
        }

        ContentCache::flush();
        $this->components->twoColumnDetail('Cached page content', '<fg=green>new generation</>');

        if ($this->option('rebuild')) {
            foreach (['config:cache' => 'Config cache', 'route:cache' => 'Route cache', 'view:cache' => 'Compiled views'] as $command => $label) {
                $this->step($command, $label, 'rebuilt', $failed);
            }
        }

        if ($failed !== []) {
            $this->components->error('Failed: '.implode(', ', $failed));

            return self::FAILURE;
        }
        $this->components->info('Done. Running PHP workers keep their opcode cache until the container restarts.');

        return self::SUCCESS;
    }

    /** @param list<string> $failed */
    private function step(string $command, string $label, string $done, array &$failed): void
    {
        $code = $this->callSilently($command);
        $this->components->twoColumnDetail($label, $code === 0 ? "<fg=green>{$done}</>" : '<fg=red>failed</>');
        if ($code !== 0) {
            $failed[] = $command;
        }
    }
}
