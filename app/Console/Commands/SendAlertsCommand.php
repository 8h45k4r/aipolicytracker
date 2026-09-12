<?php

namespace App\Console\Commands;

use App\Mail\DailyAlertMail;
use App\Models\AlertDelivery;
use App\Models\Follow;
use App\Models\User;
use App\Services\Alerts\AlertBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

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

    protected $description = 'Send daily change and deadline alerts to Pro accounts for the records they follow';

    public function handle(AlertBuilder $builder): int
    {
        $now = now();
        $today = $now->toDateString();
        $sent = $skipped = $empty = 0;
        $userIds = Follow::query()->distinct()->pluck('user_id');
        User::whereIn('id', $userIds)->orderBy('id')->chunk(100, function ($users) use ($builder, $now, $today, &$sent, &$skipped, &$empty) {
            foreach ($users as $user) {
                if (! $user->entitled('alerts.daily') || AlertDelivery::where('user_id', $user->id)->where('sent_on', $today)->exists()) {
                    $skipped++;

                    continue;
                }
                $last = AlertDelivery::where('user_id', $user->id)->max('window_end');
                $since = $last ? \Carbon\Carbon::parse($last)->max($now->copy()->subDays(7)) : $now->copy()->subDay();
                $digest = $builder->build($user, $since, $now);
                if ($digest['changes']->isEmpty() && ! $digest['milestone']) {
                    $empty++;

                    continue;
                }
                if (! $this->option('dry-run')) {
                    AlertDelivery::create(['user_id' => $user->id, 'sent_on' => $today, 'window_start' => $since, 'window_end' => $now, 'changes_count' => $digest['changes']->count(), 'deadlines_count' => $digest['deadlines']->count()]);
                    Mail::to($user->email)->send(new DailyAlertMail($user, $digest['changes'], $digest['deadlines'], $since, $now));
                }
                $sent++;
            }
        });
        $this->info("Alerts: {$sent} sent, {$empty} with nothing new, {$skipped} skipped (not entitled or already sent today).");

        return self::SUCCESS;
    }
}
