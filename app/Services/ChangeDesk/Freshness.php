<?php

namespace App\Services\ChangeDesk;

use App\Models\ChangeEvent;
use App\Models\SourceCheck;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * How quickly a change reaches the site: days from the official text's date to the day
 * the change first appeared here. The official date is the source document's date, or
 * the event date where the record has none.
 *
 * Measured only for changes that happened since the desk started (config
 * changedesk.measured_since) and were first published since: earlier first-publication
 * dates come from a bulk import, and a change dated before the start that is recorded
 * now is research filling in the past, not the desk missing a change. Counting either
 * would describe the backlog, not the desk.
 */
final class Freshness
{
    public static function since(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) config('changedesk.measured_since'))->startOfDay();
    }

    public static function targetDays(): int
    {
        return (int) config('changedesk.target_days', 3);
    }

    /** @return Collection<int, array{change: ChangeEvent, official: CarbonImmutable, lag: int}> */
    public static function measured(?CarbonImmutable $from = null): Collection
    {
        $from = max($from ?? self::since(), self::since());

        return ChangeEvent::published()
            ->whereNotNull('first_published_at')
            ->where('first_published_at', '>=', $from)
            ->orderByDesc('first_published_at')
            ->get(['id', 'slug', 'aipt_id', 'title', 'occurred_on', 'source_document_date', 'first_published_at', 'jurisdiction_id'])
            ->map(function (ChangeEvent $c) {
                $official = CarbonImmutable::parse($c->source_document_date ?? $c->occurred_on)->startOfDay();

                return ['change' => $c, 'official' => $official, 'lag' => max(0, (int) $official->diffInDays(CarbonImmutable::parse($c->first_published_at)->startOfDay()))];
            })
            ->filter(fn ($r) => $r['official']->gte(self::since()))
            ->values();
    }

    /** @return array{count: int, median: ?float, within_target: ?int, p90: ?int} */
    public static function summary(Collection $rows): array
    {
        $lags = $rows->pluck('lag')->sort()->values();
        $n = $lags->count();
        if ($n === 0) {
            return ['count' => 0, 'median' => null, 'within_target' => null, 'p90' => null];
        }
        $median = $n % 2 ? (float) $lags[intdiv($n, 2)] : ($lags[$n / 2 - 1] + $lags[$n / 2]) / 2;

        return [
            'count' => $n,
            'median' => $median,
            'within_target' => (int) round(100 * $lags->filter(fn ($d) => $d <= self::targetDays())->count() / $n),
            'p90' => (int) $lags[(int) min($n - 1, ceil(0.9 * $n) - 1)],
        ];
    }

    /** Monthly rows, newest first: month label, changes, median, % within target, source checks. */
    public static function byMonth(int $months = 6): array
    {
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths($i);
            if ($start->endOfMonth()->lt(self::since())) {
                break;
            }
            $rows = self::measured($start)->filter(fn ($r) => CarbonImmutable::parse($r['change']->first_published_at)->lt($start->addMonth()));
            $out[] = ['month' => $start->format('F Y'), 'checks' => SourceCheck::whereBetween('checked_at', [$start, $start->endOfMonth()])->count()] + self::summary($rows);
        }

        return $out;
    }
}
