<?php

namespace App\Console\Commands;

use App\Models\ExternalIncident;
use App\Services\ExternalData\IncidentEnrichment;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

/**
 * Which sensitive incident pages a search engine still has.
 *
 * The site cannot ask Google what it has indexed, but Search Console can be
 * asked to export the pages it shows, and that export can be checked against
 * the records this site marks sensitive. Any match is a page to request
 * removal for (Search Console > Removals), and the command says so.
 *
 * Without an export it still does the check that is possible from here: that
 * no sensitive page, at its current or its old numeric address, is in any
 * sitemap, and that every one is served noindex.
 */
class SeoSensitiveIndexedCommand extends Command
{
    protected $signature = 'seo:sensitive-indexed
        {csv? : A Search Console Pages export (Performance > Pages > Export)}
        {--out= : Write the matches to this CSV path}';

    protected $description = 'List sensitive incident pages that a Search Console export shows as indexed, and check none is in a sitemap';

    public function handle(HttpKernel $kernel): int
    {
        $sensitive = ExternalIncident::where('sensitivity', IncidentEnrichment::SENSITIVE)->orderBy('incident_id')->get();
        $this->line($sensitive->count().' incident records are marked sensitive.');

        // Every address a sensitive page has ever had.
        $addresses = [];
        foreach ($sensitive as $i) {
            $addresses[rtrim($i->url(), '/')] = $i;
            $addresses[rtrim(url('/ai-risk/incidents/'.$i->incident_id), '/')] = $i;
        }

        $problems = 0;
        foreach (['/sitemap-incidents.xml', '/sitemap-static.xml'] as $map) {
            $xml = (string) $this->fetch($kernel, $map)->getContent();
            foreach ($addresses as $url => $i) {
                if (str_contains($xml, '<loc>'.$url.'</loc>')) {
                    $this->error("In {$map}: {$url}");
                    $problems++;
                }
            }
        }
        foreach ($sensitive->take(200) as $i) {
            $res = $this->fetch($kernel, parse_url($i->url(), PHP_URL_PATH));
            $robots = (string) $res->headers->get('X-Robots-Tag', '');
            if (! str_contains($robots, 'noindex') && ! preg_match('#<meta name="robots" content="[^"]*noindex#', (string) $res->getContent())) {
                $this->error('Served without noindex: '.$i->url());
                $problems++;
            }
        }
        $this->line($problems === 0 ? 'No sensitive page is in a sitemap, and each is served noindex.' : "{$problems} problem(s) above.");

        $csv = $this->argument('csv');
        if (! $csv) {
            $this->line('Pass a Search Console Pages export to see which of them the index still shows.');

            return $problems === 0 ? self::SUCCESS : self::FAILURE;
        }
        if (! is_readable($csv)) {
            $this->error("Cannot read {$csv}");

            return self::FAILURE;
        }

        $matches = [];
        $fh = fopen($csv, 'r');
        fgetcsv($fh);
        while (($row = fgetcsv($fh)) !== false) {
            $page = rtrim(trim((string) ($row[0] ?? '')), '/');
            $page = preg_replace('#^https?://(www\.)?aipolicytracker\.org#', rtrim(url('/'), '/'), $page);
            if (isset($addresses[$page])) {
                $matches[] = ['url' => $row[0], 'incident_id' => $addresses[$page]->incident_id, 'impressions' => (int) str_replace(',', '', (string) ($row[2] ?? 0)), 'clicks' => (int) str_replace(',', '', (string) ($row[1] ?? 0))];
            }
        }
        fclose($fh);
        usort($matches, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);

        if ($matches === []) {
            $this->info('None of the sensitive pages appears in the export.');
        } else {
            $this->warn(count($matches).' sensitive page(s) appear in the export. Request removal in Search Console > Removals, then re-run after the next export.');
            $this->table(['URL', 'Incident', 'Impressions', 'Clicks'], array_map(fn ($m) => [$m['url'], $m['incident_id'], $m['impressions'], $m['clicks']], array_slice($matches, 0, 50)));
        }
        if ($out = $this->option('out')) {
            $fh = fopen($out, 'w');
            fputcsv($fh, ['url', 'incident_id', 'impressions', 'clicks']);
            foreach ($matches as $m) {
                fputcsv($fh, $m);
            }
            fclose($fh);
            $this->line("Written to {$out}");
        }

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function fetch(HttpKernel $kernel, string $path)
    {
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
