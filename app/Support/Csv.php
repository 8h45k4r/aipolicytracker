<?php

namespace App\Support;

/**
 * CSV cells that a spreadsheet will not execute.
 *
 * A cell starting with `=`, `+`, `-`, `@`, a tab or a carriage return is read by
 * Excel and Sheets as a formula. Exports carry text typed by other people (readers,
 * AI Incident Database submitters), so such a cell is prefixed with `'` and opens
 * as plain text.
 */
final class Csv
{
    public static function cell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'".$value : $value;
    }

    /**
     * @param  array<mixed>  $cells
     * @return list<mixed>
     */
    public static function row(array $cells): array
    {
        return array_map(self::cell(...), array_values($cells));
    }
}
