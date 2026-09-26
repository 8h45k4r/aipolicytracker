<?php

namespace App\Console\Commands;

use App\Mail\WeeklyDigestMail;
use App\Models\ChangeEvent;
use App\Models\Deadline;
use App\Models\DigestIssue;
use App\Models\ExternalIncident;
use App\Models\Subscriber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the weekly digest to confirmed subscribers: published change events from
 * the last 7 days (filtered by each subscriber's topics) plus application dates in
 * the next 60 days. Idempotent per period: subscribers already sent to since the
 * period started are skipped.
 */
class SendDigestCommand extends Command
{
    protected $signature = 'digest:send {--days=7 : Look-back window in days} {--dry-run : Report without sending}';

    protected $description = 'Send the weekly AI policy digest to confirmed subscribers';

    public function handle(): int
    {
        $since = now()->subDays((int) $this->option('days'))->startOfDay();
        $changes = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->where('occurred_on', '>=', $since->toDateString())->orderByDesc('occurred_on')->get();
        $deadlines = Deadline::with('policyInstrument.jurisdiction')->whereBetween('due_on', [now()->toDateString(), now()->addDays(60)->toDateString()])->orderBy('due_on')->limit(8)->get();
        $period = $since->format('j M').' – '.now()->format('j M Y');
        $incidents = ExternalIncident::where('occurred_on', '>=', $since->toDateString())->orderByDesc('occurred_on')->limit(5)->get();
        $incidentCount = ExternalIncident::where('occurred_on', '>=', $since->toDateString())->count();

        $sent = $skipped = 0;
        Subscriber::active()->orderBy('id')->chunk(200, function ($subscribers) use ($changes, $deadlines, $period, $since, $incidents, $incidentCount, &$sent, &$skipped) {
            foreach ($subscribers as $subscriber) {
                if ($subscriber->last_sent_at && $subscriber->last_sent_at->gte($since)) {
                    $skipped++;

                    continue;
                }
                $mine = $changes->filter(fn ($c) => $subscriber->wants($c))->values();
                if ($mine->isEmpty() && $deadlines->isEmpty() && $incidents->isEmpty()) {
                    $skipped++;

                    continue;
                }
                if (! $this->option('dry-run')) {
                    Mail::to($subscriber->email)->send(new WeeklyDigestMail($subscriber, $mine, $deadlines, $period, $incidents, $incidentCount));
                    $subscriber->update(['last_sent_at' => now()]);
                }
                $sent++;
            }
        });
        // The issue as sent, kept for the archive at /newsletter. Recorded even
        // when nobody was mailed this week: the issue is what was published, and
        // the archive should not have holes where the list was small.
        if (! $this->option('dry-run') && ($changes->isNotEmpty() || $deadlines->isNotEmpty())) {
            DigestIssue::forDay(now()->toDateString())->fill([
                'period_start' => $since->toDateString(),
                'period_end' => now()->toDateString(),
                'change_ids' => $changes->pluck('id')->all(),
                'deadline_ids' => $deadlines->pluck('id')->all(),
                'incident_count' => $incidentCount,
                'recipients' => $sent,
            ])->save();
        }
        $this->info("Digest: {$sent} sent, {$skipped} skipped, {$changes->count()} changes in window ({$period}).");

        return self::SUCCESS;
    }
}
