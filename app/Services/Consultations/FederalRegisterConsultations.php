<?php

namespace App\Services\Consultations;

use App\Models\Consultation;
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
    private const FIELDS = ['title', 'document_number', 'type', 'agencies', 'abstract', 'html_url', 'comment_url', 'publication_date', 'comments_close_on'];

    /** @return array{seen:int, kept:int, created:int, updated:int, reopened:int} */
    public function sync(): array
    {
        $config = config('consultations.federal_register');
        $relevance = (string) config('consultations.relevance');
        $counts = ['seen' => 0, 'kept' => 0, 'created' => 0, 'updated' => 0, 'reopened' => 0];
        $done = [];

        foreach ($config['terms'] as $term) {
            for ($page = 1; $page <= (int) $config['max_pages']; $page++) {
                // The API wants fields[]=a&fields[]=b; the HTTP client's array encoding (fields[0]=a) is refused.
                $query = http_build_query(['per_page' => 100, 'page' => $page, 'order' => 'newest',
                    'conditions' => ['term' => $term, 'comment_date' => ['gte' => now()->toDateString()]]])
                    .'&'.implode('&', array_map(fn ($f) => 'fields%5B%5D='.$f, self::FIELDS));
                $response = Http::timeout((int) $config['timeout'])->acceptJson()->get($config['base_url'].'?'.$query);
                if (! $response->successful()) {
                    throw new RuntimeException('Federal Register API answered '.$response->status());
                }
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

    private function store(array $doc, array &$counts): void
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
            'comments_close_on' => $doc['comments_close_on'],
            'fetched_at' => now(),
        ];
        $existing = Consultation::where('source', 'federal_register')->where('external_id', $doc['document_number'])->first();
        if (! $existing) {
            Consultation::create($values + ['source' => 'federal_register', 'external_id' => $doc['document_number'], 'status' => 'collected']);
            $counts['created']++;

            return;
        }
        $dateMoved = $existing->comments_close_on?->toDateString() !== Carbon::parse($values['comments_close_on'])->toDateString();
        if ($dateMoved && $existing->status === 'confirmed') {
            $values += ['status' => 'collected', 'reviewed_by' => null, 'reviewed_at' => null];
            $counts['reopened']++;
        }
        $existing->update($values);
        $counts['updated']++;
    }
}
