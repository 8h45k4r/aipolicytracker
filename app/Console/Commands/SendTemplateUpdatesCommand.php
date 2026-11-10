<?php

namespace App\Console\Commands;

use App\Services\Templates\TemplateUpdateNotices;
use Illuminate\Console\Command;

/**
 * Sends the "new version of a template you asked about" emails that templates:build
 * queued. Each notice is claimed before it is sent, so two runs never mail it twice;
 * a failure is retried with backoff, up to three attempts.
 */
class SendTemplateUpdatesCommand extends Command
{
    protected $signature = 'templates:notify {--limit=200 : Most notices to send in this run}';

    protected $description = 'Email readers who asked to hear about new versions of a template';

    public function handle(TemplateUpdateNotices $notices): int
    {
        $c = $notices->send(max(1, (int) $this->option('limit')));
        $this->info(sprintf('%d sent, %d to retry, %d failed, %d suppressed (unsubscribed).', $c['sent'], $c['retry'], $c['failed'], $c['suppressed']));

        return self::SUCCESS;
    }
}
