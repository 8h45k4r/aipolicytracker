<?php

namespace App\Console\Commands;

use App\Mail\DailyAlertMail;
use App\Models\AlertChannel;
use App\Models\AlertDelivery;
use App\Models\ApplicabilityProfile;
use App\Models\Follow;
use App\Models\User;
use App\Services\Alerts\AlertBuilder;
use App\Services\Alerts\WebhookDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends the daily alert to accounts entitled to it (alerts.daily) that follow
 * at least one record. The window runs from the previous delivery (capped at
 * seven days) to now; an email goes out only when it has something to say:
 * a change in the window or an application date at a milestone. One delivery
 * per user per day, recorded before sending so a re-run cannot double-send.
 */
class SendAlertsCommand extends Command
{
    protected $signature = 'alerts:send {--dry-run : Report without sending}';

    protected $description = 'Send daily change and deadline alerts to Pro accounts for the records they follow and the profiles they saved';

    public function handle(AlertBuilder $builder, WebhookDispatcher $dispatcher): int
    {
        $now = now();
        $today = $now->toDateString();
        $sent = $skipped = $empty = 0;
        $userIds = Follow::query()->distinct()->pluck('user_id')
            ->merge(ApplicabilityProfile::query()->distinct()->pluck('user_id'))->unique()->values();
        User::whereIn('id', $userIds)->orderBy('id')->chunk(100, function ($users) use ($builder, $dispatcher, $now, $today, &$sent, &$skipped, &$empty) {
            foreach ($users as $user) {
                if (! $user->entitled('alerts.daily') || AlertDelivery::where('user_id', $user->id)->where('sent_on', $today)->exists()) {
                    $skipped++;

                    continue;
                }
                $last = AlertDelivery::where('user_id', $user->id)->max('window_end');
                $since = $last ? Carbon::parse($last)->max($now->copy()->subDays(7)) : $now->copy()->subDay();
                $digest = $builder->build($user, $since, $now);
                if ($digest['changes']->isEmpty() && ! $digest['milestone']) {
                    $empty++;

                    continue;
                }
                if (! $this->option('dry-run')) {
                    $delivery = AlertDelivery::create(['user_id' => $user->id, 'sent_on' => $today, 'window_start' => $since, 'window_end' => $now, 'changes_count' => $digest['changes']->count(), 'deadlines_count' => $digest['deadlines']->count()]);
                    // The inbox is on unless the account turned it off (unsubscribe link or the alerts page).
                    if (AlertChannel::where('user_id', $user->id)->where('kind', 'email')->value('enabled') ?? true) {
                        Mail::to($user->email)->send(new DailyAlertMail($user, $digest['changes'], $digest['deadlines'], $since, $now, $digest['reasons']));
                    }
                    $channels = AlertChannel::where('user_id', $user->id)->whereIn('kind', ['slack', 'webhook'])->where('enabled', true)->get();
                    if ($channels->isNotEmpty()) {
                        $payload = WebhookDispatcher::payload($digest, 'AI policy alert: '.$digest['changes']->count().' '.Str::plural('change', $digest['changes']->count()).', '.$digest['deadlines']->count().' upcoming '.Str::plural('date', $digest['deadlines']->count()), route('following.index'));
                        foreach ($channels as $channel) {
                            $dispatcher->attempt($dispatcher->queue($channel, $payload, $delivery->id));
                        }
                    }
                    if ($digest['reasons'] !== []) {
                        ApplicabilityProfile::where('user_id', $user->id)->update(['last_matched_at' => $now]);
                    }
                }
                $sent++;
            }
        });
        $retried = $dispatcher->deliverDue();
        $this->info("Alerts: {$sent} sent, {$empty} with nothing new, {$skipped} skipped (not entitled or already sent today).");

        return self::SUCCESS;
    }
}
