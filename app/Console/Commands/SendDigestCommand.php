<?php

namespace App\Console\Commands;

use App\Mail\WeeklyDigestMail;
use App\Models\ChangeEvent;
use App\Models\Deadline;
use App\Models\DigestIssue;
use App\Models\ExternalIncident;
use App\Models\Subscriber;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the weekly digest to confirmed subscribers: the published changes since the
 * previous issue (filtered by each subscriber's topics) plus scheduled application
 * dates in the next 60 days. Idempotent per period: a subscriber mailed in the last
 * days-1 days is skipped, so a re-run the same week sends nothing twice.
 */
class SendDigestCommand extends Command
{
    protected $signature = 'digest:send {--days=7 : Look-back window in days} {--dry-run : Report without sending}';

    protected $description = 'Send the weekly AI policy digest to confirmed subscribers';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        [$since, $changes] = $this->window($days);
        // Only dates of published instruments that are still scheduled: a draft's or a
        // superseded date would be mailed to everyone and kept in the public archive.
        $deadlines = Deadline::with('policyInstrument.jurisdiction')
            ->whereHas('policyInstrument', fn ($q) => $q->published())
            ->where('deadline_status', 'scheduled')
            ->whereBetween('due_on', [now()->toDateString(), now()->addDays(60)->toDateString()])->orderBy('due_on')->limit(8)->get();
        $period = $since->format('j M').' – '.now()->format('j M Y');
        $incidents = ExternalIncident::standard()->where('occurred_on', '>=', $since->toDateString())->orderByDesc('occurred_on')->limit(5)->get();
        $incidentCount = ExternalIncident::where('occurred_on', '>=', $since->toDateString())->count();
        // Already mailed this period: anyone sent to in the last days-1 days. The test used
        // to be "since the window opened", and the window opens at midnight a week back,
        // so last week's 07:00 send counted as this week's and every other issue was skipped.
        $recent = now()->subDays(max(1, $days - 1))->startOfDay();

        $sent = $skipped = 0;
        Subscriber::active()->orderBy('id')->chunk(200, function ($subscribers) use ($changes, $deadlines, $period, $recent, $incidents, $incidentCount, &$sent, &$skipped) {
            foreach ($subscribers as $subscriber) {
                if ($subscriber->last_sent_at && $subscriber->last_sent_at->gte($recent)) {
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
                'deadlines' => DigestIssue::snapshotDeadlines($deadlines),
                'incident_count' => $incidentCount,
                'recipients' => $sent,
            ])->save();
        }
        $this->info($this->option('dry-run')
            ? "Digest dry run, nothing sent: {$sent} would be sent, {$skipped} skipped, {$changes->count()} changes in window ({$period})."
            : "Digest: {$sent} sent, {$skipped} skipped, {$changes->count()} changes in window ({$period}).");

        return self::SUCCESS;
    }

    /**
     * The changes this issue covers, and when its period starts.
     *
     * After a previous issue: changes dated from that issue's last day on, plus any
     * that appeared since it was sent though dated earlier (imported late or
     * backdated, within the last 60 days), less those it already carried. The first
     * issue, or one after a long pause, covers the last `$days` days by event date.
     * Publication time alone is not used as the window: on a database built from
     * scratch every change was "first published" at build time.
     *
     * @return array{0: Carbon, 1: Collection<int, ChangeEvent>}
     */
    private function window(int $days): array
    {
        $base = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->whereDate('occurred_on', '<=', now()->toDateString());
        $previous = DigestIssue::whereDate('sent_on', '<', now()->toDateString())
            ->whereDate('sent_on', '>=', now()->subDays(4 * $days)->toDateString())
            ->orderByDesc('sent_on')->first();
        if (! $previous) {
            $since = now()->subDays($days)->startOfDay();

            return [$since, $base->whereDate('occurred_on', '>=', $since->toDateString())->orderByDesc('occurred_on')->get()];
        }

        $from = $previous->period_end ?? $previous->sent_on;
        $changes = $base
            ->where(fn ($q) => $q->whereDate('occurred_on', '>=', $from->toDateString())
                ->orWhere(fn ($late) => $late->where('first_published_at', '>', $previous->created_at)
                    ->whereDate('occurred_on', '>=', now()->subDays(60)->toDateString())))
            ->whereNotIn('id', $previous->change_ids ?? [])
            ->orderByDesc('occurred_on')->get();

        return [$from->copy()->startOfDay(), $changes];
    }
}
