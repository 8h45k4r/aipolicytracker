<?php

namespace App\Console\Commands;

use App\Support\PageTitle;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

/**
 * Pages that already rank on the first page and are not being clicked.
 *
 * Reads a Search Console performance export and lists rows at position ≤10 with
 * at least 100 impressions and a CTR under 2%: the cheapest traffic this site
 * can win, because the ranking is already earned and only the snippet is
 * failing. Each row is shown beside the title this build now serves, and a
 * suggested rewrite.
 *
 * Search Console's own export writes pages and queries to separate files that
 * cannot be joined. This accepts either, and a file carrying both columns (the
 * Search Console API, or a Looker Studio export) as well; with both it can say
 * which query a page is shown for and whether the title contains it.
 *
 * Run through scripts/seo/ctr-audit.
 */
class SeoCtrAuditCommand extends Command
{
    protected $signature = 'seo:ctr-audit
        {csv* : Search Console export(s): Pages.csv, Queries.csv, or a page+query export}
        {--max-position=10 : Only rows ranking at or above this average position}
        {--min-impressions=100 : Only rows with at least this many impressions}
        {--max-ctr=2 : Only rows with a CTR below this percentage}
        {--out= : Also write the findings to this CSV path}';

    protected $description = 'List pages and queries that rank on page one but are not clicked, with suggested title rewrites';

    public function handle(HttpKernel $kernel): int
    {
        $rows = [];
        foreach ((array) $this->argument('csv') as $path) {
            if (! is_readable($path)) {
                $this->error("Cannot read {$path}");

                return self::FAILURE;
            }
            array_push($rows, ...$this->read($path));
        }
        if ($rows === []) {
            $this->warn('No rows found. Export from Search Console > Performance > Export > Download CSV, and pass Pages.csv or Queries.csv.');

            return self::FAILURE;
        }

        $flagged = array_values(array_filter($rows, fn ($r) => $r['position'] <= (float) $this->option('max-position')
            && $r['impressions'] >= (int) $this->option('min-impressions')
            && $r['ctr'] < (float) $this->option('max-ctr')));
        usort($flagged, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);

        $queriesByPage = [];
        foreach ($rows as $r) {
            if ($r['page'] !== null && $r['query'] !== null) {
                $queriesByPage[$r['page']][] = $r;
            }
        }

        $findings = [];
        $sitemapTitles = null;
        foreach ($flagged as $r) {
            if ($r['page'] !== null) {
                $current = $this->currentTitle($kernel, $r['page']);
                $queries = $queriesByPage[$r['page']] ?? [];
                usort($queries, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);
                $top = $r['query'] ?? ($queries[0]['query'] ?? null);
                $findings[] = $r + [
                    'current_title' => $current,
                    'top_query' => $top,
                    'query_in_title' => $top !== null && $current !== null ? $this->covers($current, $top) : null,
                    'suggestion' => $this->suggest($current, $top),
                ];
            } else {
                $sitemapTitles ??= $this->sitemapTitles($kernel);
                [$url, $title] = $this->bestMatch($r['query'], $sitemapTitles);
                $findings[] = array_merge($r, [
                    'current_title' => $title,
                    'page' => $url,
                    'top_query' => $r['query'],
                    'query_in_title' => $title !== null ? $this->covers($title, $r['query']) : null,
                    'suggestion' => $this->suggest($title, $r['query']),
                ]);
            }
        }

        $this->line(sprintf('%d of %d rows rank at position ≤%s with ≥%s impressions and CTR <%s%%.', count($findings), count($rows), $this->option('max-position'), $this->option('min-impressions'), $this->option('max-ctr')));
        $this->table(
            ['Page', 'Query', 'Pos', 'Impr.', 'CTR', 'Title now', 'In title?', 'Suggested'],
            array_map(fn ($f) => [
                $f['page'] !== null ? (parse_url($f['page'], PHP_URL_PATH) ?: $f['page']) : '—',
                $f['top_query'] ?? '—',
                number_format($f['position'], 1),
                number_format($f['impressions']),
                number_format($f['ctr'], 1).'%',
                $f['current_title'] ?? '(not on this site)',
                $f['query_in_title'] === null ? '—' : ($f['query_in_title'] ? 'yes' : 'NO'),
                $f['suggestion'] ?? '—',
            ], array_slice($findings, 0, 60)),
        );
        if (count($findings) > 60) {
            $this->line('… '.(count($findings) - 60).' more; pass --out to write them all.');
        }

        if ($out = $this->option('out')) {
            $fh = fopen($out, 'w');
            fputcsv($fh, ['page', 'query', 'position', 'impressions', 'clicks', 'ctr_percent', 'current_title', 'query_in_title', 'suggested_title']);
            foreach ($findings as $f) {
                fputcsv($fh, [$f['page'], $f['top_query'], $f['position'], $f['impressions'], $f['clicks'], $f['ctr'], $f['current_title'], $f['query_in_title'] === null ? '' : ($f['query_in_title'] ? 'yes' : 'no'), $f['suggestion']]);
            }
            fclose($fh);
            $this->info("Written to {$out}");
        }

        return self::SUCCESS;
    }

    /**
     * Rows from one export, whatever its language or column order. Columns are
     * found by name where the header is English, and by Search Console's fixed
     * order (key, clicks, impressions, CTR, position) where it is not.
     *
     * @return list<array{page:?string, query:?string, clicks:int, impressions:int, ctr:float, position:float}>
     */
    private function read(string $path): array
    {
        $fh = fopen($path, 'r');
        $header = fgetcsv($fh) ?: [];
        $header = array_map(fn ($h) => mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $find = function (array $needles) use ($header): ?int {
            foreach ($header as $i => $h) {
                foreach ($needles as $n) {
                    if (str_contains($h, $n)) {
                        return $i;
                    }
                }
            }

            return null;
        };
        $col = [
            'page' => $find(['page', 'url', 'landing']),
            'query' => $find(['quer', 'keyword']),
            'clicks' => $find(['click']),
            'impressions' => $find(['impression']),
            'ctr' => $find(['ctr']),
            'position' => $find(['position']),
        ];
        if ($col['clicks'] === null || $col['impressions'] === null) {
            // Not an English header: Search Console's fixed column order, with
            // the first column a page if its first value is a URL, else a query.
            $col = ['page' => null, 'query' => null, 'clicks' => 1, 'impressions' => 2, 'ctr' => 3, 'position' => 4];
            $sample = fgetcsv($fh);
            $col[$sample !== false && str_starts_with((string) $sample[0], 'http') ? 'page' : 'query'] = 0;
            rewind($fh);
            fgetcsv($fh);
        }

        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }
            $get = fn (?int $i) => $i === null ? null : trim((string) ($line[$i] ?? ''));
            $impressions = (int) str_replace([',', ' '], '', (string) $get($col['impressions']));
            if ($impressions === 0 && ! is_numeric(str_replace([',', ' '], '', (string) $get($col['impressions'])))) {
                continue;
            }
            $clicks = (int) str_replace([',', ' '], '', (string) $get($col['clicks']));
            $ctrRaw = (string) $get($col['ctr']);
            $ctr = $ctrRaw === '' ? ($impressions > 0 ? $clicks / $impressions * 100 : 0.0) : (float) str_replace(['%', ','], ['', '.'], $ctrRaw);
            // An API export gives CTR as a fraction (0.006), the UI as a percentage ("0.6%").
            if ($ctrRaw !== '' && ! str_contains($ctrRaw, '%') && $ctr < 1 && $impressions > 0 && abs($ctr - $clicks / $impressions) < 0.0001) {
                $ctr *= 100;
            }
            $rows[] = [
                'page' => $get($col['page']) ?: null,
                'query' => $get($col['query']) ?: null,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => round($ctr, 2),
                'position' => (float) str_replace(',', '.', (string) $get($col['position'])),
            ];
        }
        fclose($fh);

        return $rows;
    }

    /** The <title> this build serves for a URL on this site, or null. */
    private function currentTitle(HttpKernel $kernel, string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && $host !== parse_url(config('app.url'), PHP_URL_HOST) && ! str_ends_with((string) $host, 'aipolicytracker.org')) {
            return null;
        }
        $path = (parse_url($url, PHP_URL_PATH) ?: '/').(($q = parse_url($url, PHP_URL_QUERY)) ? '?'.$q : '');
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        // Follow one redirect: the export may predate a slug change.
        if ($response->isRedirection() && ($to = $response->headers->get('Location'))) {
            return $this->currentTitle($kernel, $to);
        }
        if (! $response->isOk() || ! preg_match('#<title>(.*?)</title>#s', (string) $response->getContent(), $m)) {
            return null;
        }

        return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * A rewrite that leads with the query the page is shown for, when the title
     * does not already say it. A suggestion for a person to judge, not an edit:
     * the pattern in PageTitle is what the page will actually carry.
     */
    private function suggest(?string $current, ?string $query): ?string
    {
        if ($query === null) {
            return 'needs query data: pass a Queries or page+query export';
        }
        if (PageTitle::leaksIdentifier($query)) {
            return 'identifier query: no title on this site carries it any more';
        }
        if ($current === null) {
            return 'no page targets this query';
        }
        if ($current !== null && $this->covers($current, $query)) {
            return $current.'  (query already in title: test the description instead)';
        }
        $lead = mb_convert_case($query, MB_CASE_TITLE);
        $lead = preg_replace_callback('/\b(Ai|Eu|Uk|Us|Iso|Nist|Gdpr)\b/', fn ($m) => mb_strtoupper($m[1]), $lead);
        $rest = $current !== null ? preg_replace('/\s*\|\s*'.preg_quote((string) config('aipolicytracker.site_name'), '/').'$/', '', $current) : '';

        $room = PageTitle::MAX - mb_strlen($lead) - 2;

        return $rest !== '' && $room >= 15 ? $lead.': '.PageTitle::shortenWords($rest, $room) : PageTitle::shorten($lead);
    }

    /** True when every meaningful word of the query appears in the title. */
    private function covers(string $title, string $query): bool
    {
        return array_diff($this->words($query), $this->words($title)) === [];
    }

    /**
     * Whole words, lightly stemmed so "policies" meets "policy" — and no more
     * than that: "news" must not become "new" and match "New Zealand".
     *
     * @return list<string>
     */
    private function words(string $text): array
    {
        $stop = ['the', 'and', 'for', 'what', 'how', 'with', 'of', 'in', 'on', 'to', 'a', 'is'];
        $out = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (in_array($w, $stop, true)) {
                continue;
            }
            if (mb_strlen($w) > 4 && str_ends_with($w, 'ies')) {
                $w = mb_substr($w, 0, -3).'y';
            } elseif (mb_strlen($w) > 4 && str_ends_with($w, 's') && ! str_ends_with($w, 'ss')) {
                $w = mb_substr($w, 0, -1);
            }
            $out[] = $w;
        }

        return array_values(array_unique($out));
    }

    /** @return array<string,string> url => title for every sitemap URL (static and hub sections only) */
    private function sitemapTitles(HttpKernel $kernel): array
    {
        $out = [];
        foreach (['/sitemap-static.xml', '/sitemap-jurisdictions.xml', '/sitemap-policies.xml', '/sitemap-resources.xml'] as $map) {
            $req = Request::create($map, 'GET');
            $res = $kernel->handle($req);
            preg_match_all('#<loc>\s*([^<]+?)\s*</loc>#', (string) $res->getContent(), $m);
            foreach ($m[1] as $url) {
                $out[$url] = $this->currentTitle($kernel, $url) ?? '';
            }
        }

        return $out;
    }

    /**
     * The sitemap page whose title shares most words with the query, if it
     * shares at least half of them; otherwise none, rather than a weak guess
     * presented as an answer.
     *
     * @param  array<string,string>  $titles
     * @return array{0:?string,1:?string}
     */
    private function bestMatch(?string $query, array $titles): array
    {
        if ($query === null) {
            return [null, null];
        }
        $q = $this->words($query);
        $best = [null, null, 0, PHP_INT_MAX];
        foreach ($titles as $url => $title) {
            $words = $this->words($title);
            $score = count(array_intersect($q, $words));
            // On a tie the more focused title wins: "EU AI Act (2024): Status…"
            // targets "eu ai act" more squarely than a comparison of three frameworks.
            $extra = count($words) - $score;
            if ($score > $best[2] || ($score === $best[2] && $score > 0 && $extra < $best[3])) {
                $best = [$url, $title, $score, $extra];
            }
        }

        return $q !== [] && count($q) <= $best[2] * 2 ? [$best[0], $best[1]] : [null, null];
    }
}
