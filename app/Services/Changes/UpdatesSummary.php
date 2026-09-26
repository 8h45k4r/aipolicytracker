<?php

namespace App\Services\Changes;

use App\Models\ChangeEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The answer box at the top of an updates page: what a reader who typed "ai
 * policy updates" wants first, in one paragraph, computed from the records on
 * the page rather than written by hand and left to go stale.
 *
 * @phpstan-type Summary array{total:int, jurisdictions:int, in_force:int, urgent:int, high:int, binding:int, top:Collection<int,ChangeEvent>, updated_at:?CarbonInterface, latest_on:?CarbonInterface, sentence:string}
 */
final class UpdatesSummary
{
    /**
     * @param  Collection<int,ChangeEvent>  $changes  with jurisdiction and policyInstrument loaded
     * @param  string  $period  "the last 30 days", "September 2026", "26 September 2026", "in Brazil"
     * @return Summary
     */
    public static function for(Collection $changes, string $period, int $top = 5): array
    {
        $total = $changes->count();
        $jurisdictions = $changes->pluck('jurisdiction_id')->unique()->count();
        $inForce = $changes->filter(fn ($c) => in_array($c->status_after, ['in_force', 'partially_applicable'], true))->count();
        $urgent = $changes->where('impact_level', 'urgent')->count();
        $high = $changes->where('impact_level', 'high')->count();
        $binding = $changes->filter(fn ($c) => (bool) ($c->policyInstrument?->is_binding ?? false))->count();
        $latest = $changes->max('occurred_on');
        $updated = $changes->max('updated_at');

        $ranked = $changes->sortByDesc(fn ($c) => [Significance::forChange($c), $c->occurred_on?->timestamp ?? 0])->take($top)->values();

        return [
            'total' => $total,
            'jurisdictions' => $jurisdictions,
            'in_force' => $inForce,
            'urgent' => $urgent,
            'high' => $high,
            'binding' => $binding,
            'top' => $ranked,
            'updated_at' => $updated,
            'latest_on' => $latest,
            'sentence' => self::sentence($period, $total, $jurisdictions, $inForce, $urgent, $high, $binding),
        ];
    }

    private static function sentence(string $period, int $total, int $jurisdictions, int $inForce, int $urgent, int $high, int $binding): string
    {
        if ($total === 0) {
            return "No AI policy changes have been recorded for {$period}. Every entry on this site is dated and linked to an official source, so a quiet period means nothing was logged, not that nothing happened.";
        }
        $parts = [];
        if ($inForce > 0) {
            $parts[] = $inForce === 1 ? 'one instrument entered into force' : "{$inForce} instruments entered into force";
        }
        if ($binding > 0 && $binding !== $inForce) {
            $parts[] = "{$binding} concerned binding law";
        }
        if ($urgent + $high > 0) {
            $n = $urgent + $high;
            $parts[] = ($n === 1 ? 'one was' : "{$n} were").' rated '.($urgent > 0 && $high > 0 ? 'urgent or high impact' : ($urgent > 0 ? 'urgent' : 'high impact'));
        }
        $lead = ucfirst($period).': '.$total.' AI policy '.Str::plural('change', $total).' recorded across '.$jurisdictions.' '.Str::plural('jurisdiction', $jurisdictions);
        $detail = $parts === [] ? '' : ', of which '.implode(', ', $parts);

        return $lead.$detail.'. Each is dated, links to its official source, and shows what it means in practice.';
    }
}
