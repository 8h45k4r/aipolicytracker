<?php

namespace App\Services\Consultations;

use App\Models\Consultation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Comment periods on AI from the US Federal Register API: documents whose comment period
 * closes today or later, whose title or abstract is about AI.
 *
 * A new entry is stored as `collected`. An entry a reviewer confirmed goes back to
 * `collected` if the source later changes its close date, so a confirmed date is always
 * one a person has seen. A hidden entry stays hidden.
 */
class FederalRegisterConsultations
{
    private const FIELDS = ['title', 'document_number', 'type', 'agencies', 'abstract', 'html_url', 'comment_url', 'publication_date', 'comments_close_on', 'dates'];

    /** A notice of a public meeting, hearing or workshop names one in its title. */
    public const MEETING = '/\b(public meeting|meetings?|hearings?|workshops?|listening sessions?|roundtables?|webinars?|forums?|symposi(um|a))\b/i';

    private const MONTH_DATE = '/\b(January|February|March|April|May|June|July|August|September|October|November|December) (\d{1,2}), (\d{4})\b/';

    /** @return array{seen:int, kept:int, created:int, updated:int, reopened:int, meetings:int, meetings_created:int} */
    public function sync(): array
    {
        $counts = $this->syncCommentPeriods();
        $before = $counts['created'];
        $counts['meetings'] = $this->syncMeetings($counts);
        $counts['meetings_created'] = $counts['created'] - $before;
        $counts['created'] = $before;

        return $counts;
    }

    /** @return array{seen:int, kept:int, created:int, updated:int, reopened:int} */
    private function syncCommentPeriods(): array
    {
        $config = config('consultations.federal_register');
        $relevance = (string) config('consultations.relevance');
        $counts = ['seen' => 0, 'kept' => 0, 'created' => 0, 'updated' => 0, 'reopened' => 0];
        $done = [];

        foreach ($config['terms'] as $term) {
            for ($page = 1; $page <= (int) $config['max_pages']; $page++) {
                // The API wants fields[]=a&fields[]=b; the HTTP client's array encoding (fields[0]=a) is refused.
                $response = $this->get(['term' => $term, 'comment_date' => ['gte' => now()->toDateString()]], $page);
                $results = (array) $response->json('results', []);
                foreach ($results as $doc) {
                    $id = (string) ($doc['document_number'] ?? '');
                    if ($id === '' || isset($done[$id])) {
                        continue;
                    }
                    $done[$id] = true;
                    $counts['seen']++;
                    $text = ($doc['title'] ?? '').' '.($doc['abstract'] ?? '');
                    if (empty($doc['comments_close_on']) || empty($doc['html_url']) || preg_match($relevance, $text) !== 1) {
                        continue;
                    }
                    $counts['kept']++;
                    $this->store($doc, $counts);
                }
                if (count($results) < 100 || $page >= (int) $response->json('total_pages', 1)) {
                    break;
                }
            }
        }

        return $counts;
    }

    /**
     * Notices of public meetings, hearings and workshops about AI published in the last
     * 120 days, held today or later. The meeting date is the first full date in the notice's own `dates` text,
     * which is stored and shown verbatim beside it; a notice without one is skipped rather
     * than guessed.
     */
    private function syncMeetings(array &$counts): int
    {
        $config = config('consultations.federal_register');
        $relevance = (string) config('consultations.relevance');
        $kept = 0;
        $done = [];
        foreach ($config['terms'] as $term) {
            for ($page = 1; $page <= (int) $config['max_pages']; $page++) {
                $response = $this->get(['term' => $term, 'type' => ['NOTICE'], 'publication_date' => ['gte' => now()->subDays(120)->toDateString()]], $page);
                $results = (array) $response->json('results', []);
                foreach ($results as $doc) {
                    $id = (string) ($doc['document_number'] ?? '');
                    if ($id === '' || isset($done[$id])) {
                        continue;
                    }
                    $done[$id] = true;
                    $title = (string) ($doc['title'] ?? '');
                    $eventOn = self::firstDate((string) ($doc['dates'] ?? ''));
                    if (empty($doc['html_url']) || ! $eventOn || $eventOn < now()->toDateString() || preg_match(self::MEETING, $title) !== 1 || preg_match($relevance, $title.' '.($doc['abstract'] ?? '')) !== 1) {
                        continue;
                    }
                    $kept++;
                    $this->store($doc, $counts, 'meeting', $eventOn);
                }
                if (count($results) < 100 || $page >= (int) $response->json('total_pages', 1)) {
                    break;
                }
            }
        }

        return $kept;
    }

    /** The first "Month D, YYYY" in a notice's dates text, or null. */
    public static function firstDate(string $text): ?string
    {
        if (preg_match(self::MONTH_DATE, $text, $m) !== 1) {
            return null;
        }
        try {
            return Carbon::createFromFormat('F j Y', $m[1].' '.$m[2].' '.$m[3])->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function get(array $conditions, int $page): Response
    {
        $config = config('consultations.federal_register');
        // The API wants fields[]=a&fields[]=b; the HTTP client's array encoding (fields[0]=a) is refused.
        $query = http_build_query(['per_page' => 100, 'page' => $page, 'order' => 'newest', 'conditions' => $conditions])
            .'&'.implode('&', array_map(fn ($f) => 'fields%5B%5D='.$f, self::FIELDS));
        $response = Http::timeout((int) $config['timeout'])->acceptJson()->get($config['base_url'].'?'.$query);
        if (! $response->successful()) {
            throw new RuntimeException('Federal Register API answered '.$response->status());
        }

        return $response;
    }

    private function store(array $doc, array &$counts, string $kind = 'comment_period', ?string $eventOn = null): void
    {
        $values = [
            'jurisdiction_slug' => 'us',
            'title' => Str::limit(trim((string) $doc['title']), 495),
            'agency' => Str::limit(collect($doc['agencies'] ?? [])->pluck('name')->filter()->unique()->implode('; '), 295) ?: null,
            'document_type' => $doc['type'] ?? null,
            'abstract' => isset($doc['abstract']) ? Str::limit(trim((string) $doc['abstract']), 1200) : null,
            'url' => (string) $doc['html_url'],
            'comment_url' => $doc['comment_url'] ?? null,
            'published_on' => $doc['publication_date'] ?? null,
            'comments_close_on' => $doc['comments_close_on'] ?? null,
            'event_on' => $eventOn,
            'dates_text' => isset($doc['dates']) ? Str::limit(trim((string) $doc['dates']), 295) : null,
            'fetched_at' => now(),
        ];
        $existing = Consultation::where('source', 'federal_register')->where('external_id', $doc['document_number'])->where('kind', $kind)->first();
        if (! $existing) {
            Consultation::create($values + ['source' => 'federal_register', 'external_id' => $doc['document_number'], 'kind' => $kind, 'status' => 'collected']);
            $counts['created']++;

            return;
        }
        $field = $kind === 'meeting' ? 'event_on' : 'comments_close_on';
        $dateMoved = $existing->{$field}?->toDateString() !== ($values[$field] ? Carbon::parse($values[$field])->toDateString() : null);
        if ($dateMoved && $existing->status === 'confirmed') {
            $values += ['status' => 'collected', 'reviewed_by' => null, 'reviewed_at' => null];
            $counts['reopened']++;
        }
        $existing->update($values);
        $counts['updated']++;
    }
}
