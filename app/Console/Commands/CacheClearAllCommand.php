<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CacheClearAllCommand extends Command
{
    protected $signature = 'aip:cache-clear {--rebuild : Rebuild production caches after clearing}';

    protected $description = 'Forcefully clear all application caches and optionally rebuild them';

    public function handle(): int
    {
        $this->components->info('Clearing all caches…');

        $this->callSilently('cache:clear');
        $this->components->twoColumnDetail('Application cache', '<fg=green>cleared</>');

        $this->callSilently('config:clear');
        $this->components->twoColumnDetail('Config cache', '<fg=green>cleared</>');

        $this->callSilently('route:clear');
        $this->components->twoColumnDetail('Route cache', '<fg=green>cleared</>');

        $this->callSilently('view:clear');
        $this->components->twoColumnDetail('View cache', '<fg=green>cleared</>');

        $this->callSilently('event:clear');
        $this->components->twoColumnDetail('Event cache', '<fg=green>cleared</>');

        if (function_exists('opcache_reset')) {
            opcache_reset();
            $this->components->twoColumnDetail('OPcache', '<fg=green>reset</>');
        }

        if ($this->option('rebuild')) {
            $this->newLine();
            $this->components->info('Rebuilding production caches…');

            $this->callSilently('config:cache');
            $this->components->twoColumnDetail('Config cache', '<fg=green>rebuilt</>');

            $this->callSilently('route:cache');
            $this->components->twoColumnDetail('Route cache', '<fg=green>rebuilt</>');

            $this->callSilently('view:cache');
            $this->components->twoColumnDetail('View cache', '<fg=green>rebuilt</>');
        }

        $this->newLine();
        $this->components->info('Done.');

        return self::SUCCESS;
    }
}
