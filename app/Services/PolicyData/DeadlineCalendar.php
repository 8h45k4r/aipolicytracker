<?php

namespace App\Services\PolicyData;

use App\Models\Deadline;
use Illuminate\Support\Collection;

/**
 * Builds an iCalendar document of application dates so a compliance calendar
 * can subscribe once and stay current.
 *
 * Only dates a reader could act on are published: a scheduled deadline with an
 * exact day. Dates recorded as a month, a year or "to be decided" are omitted
 * rather than guessed into a day, because a calendar entry asserts precision
 * the record does not have. Passed and superseded dates are omitted too.
 *
 * Every event carries the instrument, the jurisdiction, the record's review
 * status and a link back to the page, so a reader can check the source before
 * acting on a reminder.
 */
class DeadlineCalendar
{
    /** Dates that can be placed on a calendar without inventing precision. */
    public const PUBLISHABLE_PRECISION = 'exact';

    /** Deadline states worth a reminder. */
    public const PUBLISHABLE_STATUS = ['scheduled'];

    /** Days before the date to raise an alarm in the subscriber's own client. */
    public const REMINDER_DAYS = [30, 7];

    /** @return Collection<int, Deadline> */
    public function events(?string $jurisdictionSlug = null): Collection
    {
        return Deadline::query()
            ->with(['policyInstrument.jurisdiction', 'obligation'])
            ->whereNotNull('due_on')
            ->where('date_precision', self::PUBLISHABLE_PRECISION)
            ->whereIn('deadline_status', self::PUBLISHABLE_STATUS)
            ->when($jurisdictionSlug, fn ($q) => $q->whereHas('policyInstrument.jurisdiction', fn ($j) => $j->where('slug', $jurisdictionSlug)))
            ->whereHas('policyInstrument', fn ($q) => $q->published())
            ->orderBy('due_on')
            ->get();
    }

    /** The whole feed as an iCalendar document (RFC 5545). */
    public function render(Collection $events, string $name, string $description): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.config('aipolicytracker.site_name').'//Application dates//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($name),
            'X-WR-CALDESC:'.$this->escape($description),
            // Most clients refresh a subscription on their own schedule; these ask for daily.
            'REFRESH-INTERVAL;VALUE=DURATION:P1D',
            'X-PUBLISHED-TTL:P1D',
        ];

        foreach ($events as $deadline) {
            array_push($lines, ...$this->event($deadline));
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /** @return list<string> */
    private function event(Deadline $deadline): array
    {
        $instrument = $deadline->policyInstrument;
        $jurisdiction = $instrument?->jurisdiction;
        $url = $instrument?->url() ?? route('changes.index');
        $stamp = ($deadline->updated_at ?? now())->utc()->format('Ymd\THis\Z');

        $place = $jurisdiction?->short_name ?: $jurisdiction?->name;
        $summary = trim(($place ? $place.': ' : '').$deadline->title);
        $body = collect([
            $instrument?->short_title ?: $instrument?->title,
            $deadline->description,
            $deadline->obligation ? 'Obligation: '.$deadline->obligation->title : null,
            $instrument?->review_status ? 'Record status: '.str_replace('_', ' ', $instrument->review_status) : null,
            $deadline->confidence_level ? 'Date confidence: '.$deadline->confidence_level : null,
            'Informational only, not legal advice. Check the official source before acting.',
            $deadline->official_source_url ? 'Official source: '.$deadline->official_source_url : null,
        ])->filter()->implode("\n");

        $lines = [
            'BEGIN:VEVENT',
            // Stable across rebuilds so a subscriber sees updates, not duplicates.
            'UID:deadline-'.$deadline->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$stamp,
            'DTSTART;VALUE=DATE:'.$deadline->due_on->format('Ymd'),
            'DTEND;VALUE=DATE:'.$deadline->due_on->copy()->addDay()->format('Ymd'),
            'SUMMARY:'.$this->escape($summary),
            'DESCRIPTION:'.$this->escape($body),
            'URL:'.$url,
            'CATEGORIES:'.$this->escape($jurisdiction?->name ?: 'AI policy'),
            'TRANSP:TRANSPARENT',
        ];

        foreach (self::REMINDER_DAYS as $days) {
            array_push($lines, 'BEGIN:VALARM', 'ACTION:DISPLAY', 'TRIGGER:-P'.$days.'D', 'DESCRIPTION:'.$this->escape($summary.' in '.$days.' days'), 'END:VALARM');
        }
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /** RFC 5545 text escaping. */
    private function escape(string $value): string
    {
        return str_replace(['\\', "\n", "\r", ';', ','], ['\\\\', '\\n', '', '\\;', '\\,'], trim($value));
    }

    /** RFC 5545 line folding: no content line over 75 octets. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = substr($line, 0, 75);
        $rest = substr($line, 75);
        foreach (str_split($rest, 74) as $chunk) {
            $out .= "\r\n ".$chunk;
        }

        return $out;
    }
}
