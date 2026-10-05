<?php

namespace App\Support\Admin;

use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every admin export: streamed in chunks so a large table never sits in memory, with
 * formula-looking cells neutralised (App\Support\Csv), dates as ISO strings and a UTF-8
 * byte-order mark so a spreadsheet opens accented names correctly. An export is built
 * from the same query as the list on screen, so it contains what the filters show.
 */
final class CsvStream
{
    /**
     * @param  list<string>  $header
     * @param  callable(mixed):array<mixed>  $row
     */
    public static function from(Builder $query, string $name, array $header, callable $row): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $header, $row) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            // lazy() pages with LIMIT/OFFSET and keeps the list's own ORDER BY; lazyById would
            // re-page by id under a different sort and drop or repeat rows.
            $query->lazy(500)->each(function ($model) use ($out, $row) {
                fputcsv($out, Csv::row(array_map(
                    fn ($cell) => $cell instanceof \DateTimeInterface ? $cell->format('Y-m-d H:i:s') : (is_bool($cell) ? ($cell ? 'yes' : 'no') : $cell),
                    $row($model),
                )));
            });
            fclose($out);
        }, $name.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
