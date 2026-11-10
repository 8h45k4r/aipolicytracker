<?php

namespace App\Services\Admin;

use App\Models\ChangeEvent;
use App\Models\ContributorSubmission;
use App\Models\Subscriber;
use App\Models\TemplateDownloadRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The dashboard's sense of direction: each headline figure against the 30 days before,
 * and how the audience grew week by week. Read-only counts over existing rows; nothing
 * is stored. Cached briefly because the dashboard is the admin's front page.
 */
class DashboardGrowth
{
    public const WEEKS = 13;

    /**
     * New rows in the last 30 days and in the 30 days before, per measure.
     *
     * @return array<string, array{now: int, before: int, change: ?float}>
     */
    public function deltas(): array
    {
        $measures = [
            'subscribers' => Subscriber::query()->whereNotNull('confirmed_at'),
            'accounts' => User::query(),
            'requests' => TemplateDownloadRequest::query(),
            'submissions' => ContributorSubmission::query(),
            'changes' => ChangeEvent::published(),
        ];
        $now = now();
        $out = [];
        foreach ($measures as $key => $query) {
            $recent = $this->between(clone $query, $now->copy()->subDays(30), $now);
            $before = $this->between(clone $query, $now->copy()->subDays(60), $now->copy()->subDays(30));
            $out[$key] = ['now' => $recent, 'before' => $before, 'change' => $before > 0 ? round(($recent - $before) / $before * 100) : null];
        }

        return $out;
    }

    /**
     * Active subscribers (confirmed, not unsubscribed) and registered accounts at the end of
     * each of the last 13 weeks, oldest first.
     *
     * @return array{subscribers: array<string, int>, accounts: array<string, int>}
     */
    public function weekly(): array
    {
        $ends = collect(range(self::WEEKS - 1, 0))->map(fn ($w) => CarbonImmutable::now()->subWeeks($w)->endOfDay());
        $subscribers = Subscriber::query()->whereNotNull('confirmed_at')->get(['confirmed_at', 'unsubscribed_at']);
        $accounts = User::query()->pluck('created_at');

        return [
            'subscribers' => $ends->mapWithKeys(fn ($end) => [$end->toDateString() => $subscribers->filter(
                fn ($s) => $s->confirmed_at <= $end && ($s->unsubscribed_at === null || $s->unsubscribed_at > $end)
            )->count()])->all(),
            'accounts' => $ends->mapWithKeys(fn ($end) => [$end->toDateString() => $accounts->filter(fn ($c) => $c !== null && $c <= $end)->count()])->all(),
        ];
    }

    private function between(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        $table = $query->getModel()->getTable();

        return $query->where($table.'.created_at', '>', $from)->where($table.'.created_at', '<=', $to)->count();
    }
}
