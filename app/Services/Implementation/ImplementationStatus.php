<?php

namespace App\Services\Implementation;

/**
 * The status an implementation measure is shown with.
 *
 * A record declares planned, consultation, draft, adopted or published (or
 * "unverified" while it is an empty draft). "Overdue" is never declared: it is
 * derived here, at read time, from the legal due date and today, so a record
 * that was on time at its last import does not keep saying so after the date
 * passes. A pure function: the same inputs always give the same answer.
 */
final class ImplementationStatus
{
    public const DECLARED = [
        'unverified' => 'Unverified', 'planned' => 'Planned', 'consultation' => 'In consultation', 'draft' => 'Draft text',
        'adopted' => 'Adopted', 'published' => 'Published',
    ];

    public const LABELS = self::DECLARED + ['overdue' => 'Overdue'];

    /** Declared statuses that mean the measure exists; once reached, a due date cannot make it overdue. */
    private const DONE = ['adopted', 'published'];

    /**
     * @param  string|null  $dueOn  YYYY-MM-DD, the date the instrument sets for the measure
     * @param  string  $today  YYYY-MM-DD
     */
    public static function derive(string $declared, ?string $dueOn, ?string $adoptedOn, ?string $publishedOn, string $today): string
    {
        // An unverified draft has no status read from a source; deriving "overdue" from it
        // would state a fact nobody has checked.
        if ($declared === 'unverified') {
            return 'unverified';
        }
        if (in_array($declared, self::DONE, true) || $adoptedOn !== null || $publishedOn !== null) {
            return $declared;
        }
        if ($dueOn !== null && $dueOn < $today) {
            return 'overdue';
        }

        return $declared;
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
