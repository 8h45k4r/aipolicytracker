<?php

namespace App\Services\Admin;

use App\Console\Commands\SyncAiidApiCommand;
use App\Http\Controllers\Backend\Admin\BillingController;
use App\Http\Controllers\Backend\Review\ReviewController;
use App\Models\BillingEvent;
use App\Models\ChannelDelivery;
use App\Models\ContributorSubmission;
use App\Models\JobRun;
use App\Models\PolicyInstrument;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Billing\WebhookProcessor;
use App\Services\Security\Turnstile;
use Illuminate\Support\Facades\Cache;

/**
 * What needs someone's attention now, for the top of the admin dashboard: each item says
 * what is wrong, why it matters and where to fix it, and is shown only to an account that
 * can act on it. Ordered most serious first; an empty list means all clear.
 */
final class Attention
{
    public const SEVERITY = ['critical' => 0, 'warning' => 1, 'info' => 2];

    public function __construct(private Turnstile $turnstile) {}

    /** @return list<array{severity:string, title:string, detail:string, action:string, url:string}> */
    public function items(User $user, array $jobs): array
    {
        $items = [];
        $add = function (string $capability, string $severity, string $title, string $detail, string $action, string $url) use ($user, &$items) {
            if ($user->can($capability)) {
                $items[] = compact('severity', 'title', 'detail', 'action', 'url');
            }
        };

        $failed = collect($jobs)->filter(fn ($run, $job) => JobRun::isScheduled($job) && $run && $run->finished_at && ! $run->succeeded());
        if ($failed->isNotEmpty()) {
            $names = $failed->keys()->map(fn ($j) => JobRun::JOBS[$j]['label'])->implode(', ');
            $add('jobs.run', 'critical', $failed->count() === 1 ? 'A scheduled job failed' : $failed->count().' scheduled jobs failed', $names.'. Open the job to read its output, then run it again.', 'Open jobs', route('backend.admin.jobs'));
        }

        if (config('mail.default') === 'log') {
            $add('settings.manage', 'critical', 'Email is not being sent', 'Mail goes to the log, so digests, alerts, invitations and confirmations never arrive.', 'Set up email', route('backend.admin.settings'));
        }

        $noFactor = User::whereNotNull('admin_role')->whereNull('two_factor_confirmed_at')->whereNull('suspended_at')->count();
        if ($noFactor > 0) {
            $add('users.manage', 'critical', $noFactor.' admin '.($noFactor === 1 ? 'account has' : 'accounts have').' no second factor', 'They cannot pass the admin sign-in check until they enrol an authenticator.', 'Review accounts', route('backend.admin.users.index', ['status' => 'no_factor']));
        }

        $webhookErrors = BillingEvent::where('outcome', WebhookProcessor::OUTCOME_ERROR)->count();
        if ($webhookErrors > 0) {
            $add('billing.manage', 'critical', $webhookErrors.' billing '.($webhookErrors === 1 ? 'webhook' : 'webhooks').' failed to apply', 'A subscription change from the provider was not applied, so an account may have the wrong access. Read the error, fix the cause, then re-apply.', 'Open failed webhooks', route('backend.admin.billing.index', ['tab' => 'webhooks', 'outcome' => 'error']));
        }

        $failedDeliveries = ChannelDelivery::where('status', 'failed')->where('updated_at', '>=', now()->subDays(7))->count();
        if ($failedDeliveries > 0) {
            $add('subscribers.manage', 'warning', $failedDeliveries.' Slack or webhook '.($failedDeliveries === 1 ? 'alert' : 'alerts').' failed in the last 7 days', 'All attempts were used up or the channel was disabled, so the account did not receive that alert there.', 'Open deliveries', route('backend.admin.alerts.index', ['status' => 'failed']));
        }

        $reversals = BillingEvent::whereIn('event_type', BillingController::REVERSAL_EVENTS)->where('outcome', WebhookProcessor::OUTCOME_APPLIED)->where('received_at', '>=', now()->subDays(7))->count();
        if ($reversals > 0) {
            $add('billing.manage', 'info', $reversals.' '.($reversals === 1 ? 'refund or lost dispute' : 'refunds or lost disputes').' in the last 7 days', 'A full refund or a lost chargeback on the latest payment removes Pro access.', 'Open payments', route('backend.admin.billing.index', ['tab' => 'payments', 'from' => now()->subDays(7)->toDateString()]));
        }

        $pending = ContributorSubmission::where('status', 'pending_review')->count();
        if ($pending > 0) {
            $add('submissions.decide', 'warning', $pending.' '.($pending === 1 ? 'submission is' : 'submissions are').' waiting for review', 'Corrections and sources sent by readers.', 'Review', route('backend.admin.submissions', ['status' => 'pending_review']));
        }

        $days = ReviewController::staleAfter();
        $stale = PolicyInstrument::published()->where(fn ($q) => $q->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subDays($days)))->count();
        if ($stale > 0) {
            $add('records.verify', 'warning', $stale.' '.($stale === 1 ? 'instrument is' : 'instruments are').' unverified or past '.$days.' days', 'Re-check each against its official source and record the review.', 'Show them', route('backend.review.index', ['type' => 'policy', 'review' => 'stale']));
        }

        if (! $this->turnstile->configured()) {
            $add('settings.manage', 'warning', 'Bot protection is off', 'Cloudflare Turnstile keys are not set, so public forms rely on the honeypot, email checks and rate limits only.', 'Add keys', route('backend.admin.settings').'#turnstile');
        }

        $never = collect($jobs)->filter(fn ($run, $job) => JobRun::isScheduled($job) && $run === null);
        if ($never->isNotEmpty()) {
            $add('jobs.run', 'info', $never->count().' scheduled '.($never->count() === 1 ? 'job has' : 'jobs have').' never run', $never->keys()->map(fn ($j) => JobRun::JOBS[$j]['label'])->implode(', ').'. Check the scheduler is running.', 'Open jobs', route('backend.admin.jobs'));
        }

        $unconfirmed = Subscriber::whereNull('confirmed_at')->whereNull('unsubscribed_at')->where('created_at', '<', now()->subDays(7))->count();
        if ($unconfirmed > 0) {
            $add('subscribers.manage', 'info', $unconfirmed.' '.($unconfirmed === 1 ? 'subscriber has' : 'subscribers have').' not confirmed in a week', 'Resend the confirmation or remove them.', 'Open subscribers', route('backend.admin.subscribers', ['state' => 'unconfirmed', 'to' => now()->subDays(7)->toDateString()]));
        }

        $aiid = Cache::get(SyncAiidApiCommand::LAST_RUN_KEY);
        if (($aiid['error'] ?? null) === SyncAiidApiCommand::LOGIN_REQUIRED) {
            $add('external.sync', 'info', 'The AI Incident Database live sync is paused', 'Its API now asks for a signed-in account. Incidents still update weekly from the public backup; set AIID_API_TOKEN or AIID_API_COOKIE to resume the daily sync.', 'Open external data', route('backend.admin.external'));
        }

        usort($items, fn ($a, $b) => self::SEVERITY[$a['severity']] <=> self::SEVERITY[$b['severity']]);

        return $items;
    }
}
