<?php

namespace App\Console\Commands;

use App\Support\PageTitle;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

/**
 * Crawls every URL the sitemap publishes, in process, and checks the head of
 * each page against the rules every indexable page must meet: a title of at
 * most sixty characters with no internal identifier in it, one H1, a meta
 * description of at most 155 characters, a canonical, and no identifier in the
 * H1 or the social title either.
 *
 * It reads the sitemap rather than a list of routes because the sitemap is what
 * a search engine is handed. A page that breaks a rule but is not in the
 * sitemap is a different problem (an orphan, or noindexed on purpose), and this
 * command is not the place it is found.
 *
 * No network is involved: each URL is dispatched through the HTTP kernel, so the
 * result reflects this checkout and this database, and runs in CI.
 */
class SeoAuditCommand extends Command
{
    protected $signature = 'seo:audit
        {--section=* : Only these sitemap sections (e.g. policies, incidents)}
        {--limit=0 : At most this many URLs per section; 0 for all}
        {--json= : Write every finding to this path as JSON}
        {--fail-on-violation : Exit non-zero when any rule is broken}';

    protected $description = 'Crawl the sitemap in process and report title, H1, description and identifier violations';

    public function handle(HttpKernel $kernel): int
    {
        $sections = $this->sections($kernel);
        $only = (array) $this->option('section');
        $limit = (int) $this->option('limit');

        $pages = [];
        foreach ($sections as $section => $sitemapPath) {
            if ($only !== [] && ! in_array($section, $only, true)) {
                continue;
            }
            $urls = $this->locs($this->fetch($kernel, $sitemapPath)['body']);
            if ($limit > 0) {
                $urls = array_slice($urls, 0, $limit);
            }
            $bar = $this->output->createProgressBar(count($urls));
            $bar->setFormat(" {$section}: %current%/%max%");
            foreach ($urls as $url) {
                $pages[] = ['section' => $section] + $this->inspect($kernel, $url);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        }

        $titles = array_count_values(array_filter(array_column($pages, 'title')));
        foreach ($pages as &$page) {
            if (($titles[$page['title']] ?? 0) > 1) {
                $page['problems'][] = 'duplicate title';
            }
        }
        unset($page);

        $this->report($pages);

        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->line("Findings written to {$path}");
        }

        $broken = count(array_filter($pages, fn ($p) => $p['problems'] !== []));

        return $broken > 0 && $this->option('fail-on-violation') ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string,string> section name => sitemap path */
    private function sections(HttpKernel $kernel): array
    {
        $out = [];
        foreach ($this->locs($this->fetch($kernel, '/sitemap.xml')['body']) as $loc) {
            $path = (string) parse_url($loc, PHP_URL_PATH);
            if (preg_match('#^/sitemap-([a-z]+)\.xml$#', $path, $m)) {
                $out[$m[1]] = $path;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function locs(string $xml): array
    {
        preg_match_all('#<loc>\s*([^<]+?)\s*</loc>#', $xml, $m);

        return array_map(fn ($u) => html_entity_decode($u, ENT_QUOTES | ENT_XML1), $m[1]);
    }

    /** @return array{status:int, body:string, headers:array<string,string>} */
    private function fetch(HttpKernel $kernel, string $path): array
    {
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return [
            'status' => $response->getStatusCode(),
            'body' => (string) $response->getContent(),
            'headers' => ['x-robots-tag' => (string) $response->headers->get('X-Robots-Tag', '')],
        ];
    }

    /** @return array<string,mixed> */
    private function inspect(HttpKernel $kernel, string $url): array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        $res = $this->fetch($kernel, $path.($query ? '?'.$query : ''));
        $html = $res['body'];

        $title = $this->decode($this->first('#<title>(.*?)</title>#s', $html));
        $description = $this->decode($this->first('#<meta\s+name="description"\s+content="([^"]*)"#', $html));
        $canonical = $this->first('#<link\s+rel="canonical"\s+href="([^"]*)"#', $html);
        $ogTitle = $this->decode($this->first('#<meta\s+property="og:title"\s+content="([^"]*)"#', $html));
        $robots = $this->first('#<meta\s+name="robots"\s+content="([^"]*)"#', $html).' '.$res['headers']['x-robots-tag'];
        preg_match_all('#<h1\b[^>]*>(.*?)</h1>#s', $html, $h1s);
        $h1 = array_map(fn ($h) => trim(preg_replace('/\s+/', ' ', $this->decode(strip_tags($h)))), $h1s[1]);

        $problems = [];
        if ($res['status'] !== 200) {
            $problems[] = "status {$res['status']}";
        }
        if (str_contains($robots, 'noindex')) {
            $problems[] = 'noindex page listed in sitemap';
        }
        if ($title === '') {
            $problems[] = 'no title';
        } elseif (mb_strlen($title) > PageTitle::MAX) {
            $problems[] = 'title '.mb_strlen($title).' chars';
        }
        if (PageTitle::leaksIdentifier($title)) {
            $problems[] = 'identifier in title';
        }
        if (PageTitle::leaksIdentifier($ogTitle)) {
            $problems[] = 'identifier in og:title';
        }
        if (count($h1) !== 1) {
            $problems[] = count($h1).' H1 elements';
        }
        foreach ($h1 as $heading) {
            if (PageTitle::leaksIdentifier($heading)) {
                $problems[] = 'identifier in H1';
            }
        }
        if ($description === '') {
            $problems[] = 'no meta description';
        } elseif (mb_strlen($description) > PageTitle::MAX_DESCRIPTION) {
            $problems[] = 'description '.mb_strlen($description).' chars';
        }
        if (preg_match('/&amp;(#\d+|#x[0-9a-f]+|[a-z]+);/i', $this->first('#<title>(.*?)</title>#s', $html).' '.implode(' ', $h1s[1]))) {
            $problems[] = 'double-escaped entity';
        }
        if ($canonical === '') {
            $problems[] = 'no canonical';
        }
        if (PageTitle::pathLeaksIdentifier($path)) {
            $problems[] = 'identifier in URL';
        }

        return compact('url', 'title', 'h1', 'description', 'canonical', 'problems');
    }

    private function first(string $pattern, string $html): string
    {
        return preg_match($pattern, $html, $m) ? trim($m[1]) : '';
    }

    private function decode(string $s): string
    {
        return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @param list<array<string,mixed>> $pages */
    private function report(array $pages): void
    {
        $bySection = [];
        $byProblem = [];
        foreach ($pages as $p) {
            $s = $p['section'];
            $bySection[$s]['pages'] = ($bySection[$s]['pages'] ?? 0) + 1;
            $bySection[$s]['broken'] = ($bySection[$s]['broken'] ?? 0) + ($p['problems'] === [] ? 0 : 1);
            foreach ($p['problems'] as $problem) {
                $kind = preg_replace('/^(title|description) \d+ chars$/', '$1 too long', $problem);
                $kind = preg_replace('/^\d+ H1 elements$/', 'H1 count not 1', $kind);
                $byProblem[$kind][$s] = ($byProblem[$kind][$s] ?? 0) + 1;
            }
        }

        $this->table(['Section', 'Pages', 'With a violation'], collect($bySection)->map(fn ($v, $k) => [$k, $v['pages'], $v['broken']])->values()->all());

        if ($byProblem === []) {
            $this->info('No violations.');

            return;
        }
        $rows = [];
        foreach ($byProblem as $kind => $counts) {
            arsort($counts);
            $rows[] = [$kind, array_sum($counts), collect($counts)->map(fn ($n, $s) => "{$s} {$n}")->implode(', ')];
        }
        usort($rows, fn ($a, $b) => $b[1] <=> $a[1]);
        $this->table(['Violation', 'Pages', 'Where'], $rows);
    }
}
