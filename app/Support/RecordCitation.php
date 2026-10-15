<?php

namespace App\Support;

use App\Models\Obligation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The preferred citation of one record, shared by the page's "Cite this record" block
 * and the Markdown context file, so a person and an agent cite the same state the
 * same way: the record's title, then its permanent id, record version and who
 * verified it when.
 */
final class RecordCitation
{
    /** The bracketed stamp: id, record version, verification. @return array<int, string> */
    public static function stamp(mixed $record, ?string $reviewer = null, mixed $verifiedAt = null): array
    {
        $verifiedAt = $verifiedAt ?? data_get($record, 'last_verified_at');
        if ($verifiedAt !== null && ! $verifiedAt instanceof CarbonInterface) {
            $verifiedAt = Carbon::parse($verifiedAt);
        }
        $reviewer = $reviewer ?? data_get($record, 'reviewed_by');
        $version = data_get($record, 'content_version');

        return array_values(array_filter([
            data_get($record, 'aipt_id'),
            $version ? 'record version '.$version : null,
            $verifiedAt ? 'verified '.$verifiedAt->format('j F Y').($reviewer ? ' by '.$reviewer : '') : 'not yet verified',
        ]));
    }

    /** The title a citation uses: a duty names its instrument, as its page does. */
    public static function title(mixed $record): string
    {
        $title = (string) (data_get($record, 'title') ?? data_get($record, 'name') ?? '');
        if ($record instanceof Obligation && $record->policyInstrument) {
            return $title.' ('.($record->policyInstrument->short_title ?: $record->policyInstrument->title).')';
        }

        return $title;
    }

    /** The full citation as plain text. */
    public static function text(string $title, mixed $record, string $permalink, ?string $reviewer = null, mixed $verifiedAt = null): string
    {
        return config('aipolicytracker.site_name').' ('.now()->year.'). "'.$title.'" ['.implode(', ', self::stamp($record, $reviewer, $verifiedAt)).']. '.$permalink.' (accessed '.now()->format('j F Y').').';
    }
}
