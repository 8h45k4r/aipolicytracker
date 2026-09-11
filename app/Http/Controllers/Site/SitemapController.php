<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Sitemap index plus one sitemap per content type. Only indexable pages are
 * listed; lastmod comes from real record update timestamps.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $sections = [
            'static' => PolicyInstrument::published()->max('updated_at'),
            'jurisdictions' => Jurisdiction::published()->max('updated_at'),
            'policies' => PolicyInstrument::published()->max('updated_at'),
            'obligations' => Obligation::published()->max('updated_at'),
            'changes' => ChangeEvent::published()->max('updated_at'),
            'resources' => PolicyInstrument::published()->max('updated_at'),
        ];
        $entries = collect($sections)->map(fn ($mod, $key) => ['loc' => route('sitemap.section', $key), 'lastmod' => $mod ? Carbon::parse($mod)->toAtomString() : null]);

        return $this->xml(view('site.sitemap.index', compact('entries'))->render());
    }

    public function section(string $section): Response
    {
        $urls = match ($section) {
            'static' => $this->staticUrls(),
            'jurisdictions' => Jurisdiction::published()->orderBy('slug')->get()->filter->isIndexable()->map(fn ($j) => ['loc' => $j->url(), 'lastmod' => $j->updated_at?->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.9'])->values(),
            'policies' => PolicyInstrument::published()->orderBy('slug')->get()->filter->isIndexable()->map(fn ($p) => ['loc' => $p->url(), 'lastmod' => $p->updated_at?->toAtomString(), 'changefreq' => 'weekly', 'priority' => '0.9'])->values(),
            'obligations' => Obligation::published()->with('policyInstrument')->orderBy('slug')->get()->filter(fn ($o) => filled($o->summary) && $o->policyInstrument?->isIndexable())->map(fn ($o) => ['loc' => $o->url(), 'lastmod' => $o->updated_at?->toAtomString(), 'changefreq' => 'monthly', 'priority' => '0.7'])->values(),
            'changes' => $this->changeUrls(),
            'resources' => $this->resourceUrls(),
        };

        return $this->xml(view('site.sitemap.urlset', compact('urls'))->render());
    }

    private function staticUrls()
    {
        $mod = PolicyInstrument::published()->max('updated_at');
        $lastmod = $mod ? Carbon::parse($mod)->toAtomString() : null;
        $pages = [
            [route('home'), 'daily', '1.0'], [route('policies.index'), 'daily', '0.9'], [route('jurisdictions.index'), 'weekly', '0.9'],
            [route('obligations.index'), 'weekly', '0.8'], [route('compare.index'), 'monthly', '0.7'], [route('changes.index'), 'daily', '0.9'],
            [route('tools.applicability'), 'monthly', '0.7'], [route('open-data'), 'monthly', '0.7'], [route('methodology'), 'monthly', '0.6'],
            [route('about'), 'monthly', '0.5'], [route('contribute'), 'monthly', '0.5'], [route('guides.index'), 'weekly', '0.7'],
        ];
        $urls = collect($pages)->map(fn ($p) => ['loc' => $p[0], 'lastmod' => $lastmod, 'changefreq' => $p[1], 'priority' => $p[2]]);
        // Indexable single-filter listings: one per jurisdiction and one per obligation category.
        foreach (Jurisdiction::published()->orderBy('slug')->get() as $j) {
            if ($j->policyInstruments()->published()->exists()) {
                $urls->push(['loc' => route('policies.index', ['jurisdiction' => $j->slug]), 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '0.6']);
            }
        }
        foreach (TaxonomyTerm::taxonomy('obligation_category')->get() as $c) {
            if (Obligation::published()->where('category', $c->slug)->exists()) {
                $urls->push(['loc' => route('obligations.index', ['category' => $c->slug]), 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '0.6']);
            }
        }

        return $urls->values();
    }

    private function changeUrls()
    {
        $years = ChangeEvent::published()->selectRaw('substr(occurred_on, 1, 4) as y, MAX(updated_at) as m')->groupBy('y')->orderByDesc('y')->get();

        return $years->map(fn ($r) => ['loc' => route('changes.year', $r->y), 'lastmod' => $r->m ? Carbon::parse($r->m)->toAtomString() : null, 'changefreq' => 'weekly', 'priority' => '0.6'])->values();
    }

    private function resourceUrls()
    {
        $mod = PolicyInstrument::published()->max('updated_at');
        $lastmod = $mod ? Carbon::parse($mod)->toAtomString() : null;
        $urls = collect();
        foreach (array_keys(config('content.landings')) as $slug) {
            $urls->push(['loc' => route('landing', $slug), 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '0.8']);
        }
        foreach (array_keys(config('content.guides')) as $slug) {
            $urls->push(['loc' => route('guides.show', $slug), 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.7']);
        }
        foreach (array_keys(config('content.comparisons')) as $slug) {
            $urls->push(['loc' => route('compare.show', $slug), 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.7']);
        }

        return $urls;
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
