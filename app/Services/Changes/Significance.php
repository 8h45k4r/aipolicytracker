<?php

namespace App\Services\Changes;

use App\Models\ChangeEvent;
use Carbon\CarbonInterface;

/**
 * How much a change matters, as a number from 0 to 100, so "top stories" on
 * the updates pages is a rule rather than a judgement made afresh each time.
 *
 * score() is a pure function of named facts and an age, with no model and no
 * clock inside it, so the test can state every input and the answer is the
 * same on every run. forChange() is the one place the facts are read off a
 * record. The weights are here, in the open, and are the whole policy:
 *
 *   impact level      urgent 40 · high 25 · routine 8
 *   binding instrument                                           +15
 *   status after      in force / partially applicable / adopted +12
 *                     enforcement action                        +12
 *                     proposed / under consultation             +4
 *   named reviewer confirmed the record                          +8
 *   official source on the record                                +5
 *   featured jurisdiction                                        +5
 *   age               ≤7 days +10 · ≤30 days +5 · >365 days −5
 *
 * Everything is additive and the result is clamped to 0–100, so a reader can
 * see why one item outranks another by looking at the record.
 */
final class Significance
{
    public const VERSION = 1;

    private const IMPACT = ['urgent' => 40, 'high' => 25, 'routine' => 8];

    private const STATUS = [
        'in_force' => 12, 'partially_applicable' => 12, 'adopted' => 12, 'enforcement_action' => 12,
        'proposed' => 4, 'under_consultation' => 4,
    ];

    /**
     * @param  array{impact_level?: ?string, binding?: bool, status_after?: ?string, verified?: bool, sourced?: bool, featured?: bool}  $facts
     * @param  int|null  $ageDays  days since the change occurred; null when unknown
     */
    public static function score(array $facts, ?int $ageDays): int
    {
        $score = self::IMPACT[$facts['impact_level'] ?? ''] ?? self::IMPACT['routine'];
        $score += ! empty($facts['binding']) ? 15 : 0;
        $score += self::STATUS[$facts['status_after'] ?? ''] ?? 0;
        $score += ! empty($facts['verified']) ? 8 : 0;
        $score += ! empty($facts['sourced']) ? 5 : 0;
        $score += ! empty($facts['featured']) ? 5 : 0;
        if ($ageDays !== null) {
            $score += match (true) {
                $ageDays <= 7 => 10,
                $ageDays <= 30 => 5,
                $ageDays > 365 => -5,
                default => 0,
            };
        }

        return max(0, min(100, $score));
    }

    /** The facts a change record supplies, then the score. */
    public static function forChange(ChangeEvent $change, ?CarbonInterface $asOf = null): int
    {
        $asOf ??= now();
        $age = $change->occurred_on ? (int) $change->occurred_on->startOfDay()->diffInDays($asOf->copy()->startOfDay(), false) : null;

        return self::score([
            'impact_level' => $change->impact_level,
            'binding' => (bool) ($change->policyInstrument?->is_binding ?? false),
            'status_after' => $change->status_after,
            'verified' => method_exists($change, 'isVerified') && $change->isVerified(),
            'sourced' => filled($change->official_source_url),
            'featured' => (bool) ($change->jurisdiction?->featured ?? false),
        ], $age === null || $age < 0 ? null : $age);
    }
}
