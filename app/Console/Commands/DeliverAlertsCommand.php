<?php

namespace App\Console\Commands;

use App\Services\Alerts\WebhookDispatcher;
use Illuminate\Console\Command;

/** Retries Slack and webhook deliveries that failed, with backoff, up to five attempts each. Runs hourly. */
class DeliverAlertsCommand extends Command
{
    protected $signature = 'alerts:deliver';

    protected $description = 'Retry pending Slack and webhook alert deliveries';

    public function handle(WebhookDispatcher $dispatcher): int
    {
        $result = $dispatcher->deliverDue();
        $this->info("Deliveries: {$result['sent']} sent, {$result['failed']} still pending or failed.");

        return self::SUCCESS;
    }
}
