<?php

namespace App\Services\News;

use App\Models\NewsItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

/**
 * Collects headlines from the official feeds in config/news.php. A feed that fails is
 * reported and skipped; the others still run. Items are upserted by (source, id), so a
 * repeated run changes nothing, and items older than `keep_days` are pruned.
 */
class NewsCollector
{
    /** @return array<string, array{fetched:int, kept:int, created:int, error:?string}> */
    public function sync(?string $only = null): array
    {
        $report = [];
        foreach (config('news.sources') as $key => $source) {
            if ($only && $only !== $key) {
                continue;
            }
            $row = ['fetched' => 0, 'kept' => 0, 'created' => 0, 'error' => null];
            try {
                $items = $source['format'] === 'federal_register' ? $this->federalRegister($source) : $this->feed($source);
                $row['fetched'] = count($items);
                foreach ($items as $item) {
                    if (($source['ai_only'] ?? true) && ! self::aboutAi($item['title'], $item['summary'])) {
                        continue;
                    }
                    $row['kept']++;
                    $row['created'] += $this->store($key, $source, $item) ? 1 : 0;
                }
            } catch (Throwable $e) {
                $row['error'] = class_basename($e).': '.Str::limit($e->getMessage(), 160);
            }
            $report[$key] = $row;
        }
        NewsItem::where('published_at', '<', now()->subDays((int) config('news.keep_days')))->delete();

        return $report;
    }

    /** About AI: an AI term in the title, or a specific AI phrase in the summary. */
    public static function aboutAi(string $title, string $summary): bool
    {
        return preg_match((string) config('news.relevance'), $title) === 1 || preg_match((string) config('news.relevance_summary'), $summary) === 1;
    }

    /** Topic keys whose pattern matches the text. */
    public static function topics(string $text): array
    {
        $topics = [];
        foreach (config('news.topics') as $key => $topic) {
            if (preg_match($topic['pattern'], $text) === 1) {
                $topics[] = $key;
            }
        }

        return $topics;
    }

    /** @return list<array{id:string, title:string, url:string, summary:string, published:?string}> */
    private function feed(array $source): array
    {
        $response = Http::timeout((int) config('news.timeout'))->withHeaders(['User-Agent' => 'AIPolicyTracker news collector'])->get($source['url']);
        if (! $response->successful()) {
            throw new \RuntimeException('feed answered '.$response->status());
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            throw new \RuntimeException('feed is not valid XML');
        }

        $items = [];
        if ($source['format'] === 'atom') {
            foreach ($xml->entry as $e) {
                $link = '';
                foreach ($e->link as $l) {
                    if ((string) $l['rel'] === '' || (string) $l['rel'] === 'alternate') {
                        $link = (string) $l['href'];
                        break;
                    }
                }
                $items[] = ['id' => (string) $e->id ?: $link, 'title' => trim((string) $e->title), 'url' => $link,
                    'summary' => strip_tags((string) ($e->summary ?: $e->content)), 'published' => (string) ($e->published ?: $e->updated) ?: null];
            }
        } else {
            foreach ($xml->channel->item as $i) {
                $dc = $i->children('http://purl.org/dc/elements/1.1/');
                $items[] = ['id' => (string) $i->guid ?: (string) $i->link, 'title' => trim(html_entity_decode((string) $i->title, ENT_QUOTES | ENT_HTML5)), 'url' => trim((string) $i->link),
                    'summary' => strip_tags(html_entity_decode((string) $i->description, ENT_QUOTES | ENT_HTML5)), 'published' => (string) $i->pubDate ?: ((string) $dc->date ?: null)];
            }
        }

        return $items;
    }

    /** AI documents published in the Federal Register in the last 30 days. */
    private function federalRegister(array $source): array
    {
        $query = http_build_query(['per_page' => 100, 'order' => 'newest', 'conditions' => ['term' => '"artificial intelligence"', 'publication_date' => ['gte' => now()->subDays(30)->toDateString()]]])
            .'&fields%5B%5D=document_number&fields%5B%5D=title&fields%5B%5D=abstract&fields%5B%5D=html_url&fields%5B%5D=publication_date';
        $response = Http::timeout((int) config('news.timeout'))->acceptJson()->get($source['url'].'?'.$query);
        if (! $response->successful()) {
            throw new \RuntimeException('Federal Register API answered '.$response->status());
        }

        return array_map(fn ($d) => ['id' => (string) $d['document_number'], 'title' => trim((string) $d['title']), 'url' => (string) $d['html_url'],
            'summary' => (string) ($d['abstract'] ?? ''), 'published' => $d['publication_date'] ?? null], (array) $response->json('results', []));
    }

    /** Upserts one item; true when it is new. Items without a title, an http(s) link or a date are skipped. */
    private function store(string $key, array $source, array $item): bool
    {
        if ($item['title'] === '' || ! preg_match('#^https?://#', $item['url']) || ! $item['published']) {
            return false;
        }
        try {
            $published = Carbon::parse($item['published'])->utc();
        } catch (Throwable) {
            return false;
        }
        $values = ['title' => Str::limit($item['title'], 495), 'url' => Str::limit($item['url'], 495), 'jurisdiction_slug' => $source['jurisdiction'] ?? null,
            'topics' => self::topics($item['title'].' '.$item['summary']), 'published_at' => $published, 'fetched_at' => now()];
        $existing = NewsItem::where('source', $key)->where('external_id', Str::limit($item['id'], 250, ''))->first();
        if ($existing) {
            $existing->update($values);

            return false;
        }
        NewsItem::create($values + ['source' => $key, 'external_id' => Str::limit($item['id'], 250, '')]);

        return true;
    }
}
