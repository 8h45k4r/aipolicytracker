<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\PolicyCatalog;
use App\Services\Report\StateOfAiRegulation;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Embeddable widgets: small server-rendered pages meant for an iframe, each
 * with a followed attribution link back to the record. Only /embed/* may be
 * framed by other sites (SecurityHeaders). /embed is the configurator; the
 * loader public/embed.js is optional and only sizes the frame.
 */
class EmbedController extends Controller
{
    public const KINDS = ['jurisdiction' => 'Jurisdiction card', 'deadlines' => 'Upcoming deadlines', 'map' => 'Where AI is regulated'];

    public function index(Request $request): View
    {
        $kind = array_key_exists((string) $request->query('kind'), self::KINDS) ? $request->query('kind') : 'jurisdiction';
        $jurisdiction = preg_match('/^[a-z0-9-]+$/', (string) $request->query('jurisdiction')) ? $request->query('jurisdiction') : 'eu';
        $jurisdictions = Jurisdiction::published()->withPublishedInstrument()->orderBy('name')->get()->filter->isIndexable()->values();
        $src = match ($kind) {
            'jurisdiction' => route('embed.jurisdiction', $jurisdiction),
            'deadlines' => route('embed.deadlines', array_filter(['jurisdiction' => $request->query('jurisdiction')])),
            'map' => route('embed.map'),
        };
        $height = match ($kind) {
            'jurisdiction' => 320, 'deadlines' => 360, 'map' => 520
        };
        $snippet = '<iframe src="'.$src.'" title="'.e(self::KINDS[$kind]).' from AIPolicyTracker" width="100%" height="'.$height.'" loading="lazy" style="border:1px solid #e5e7eb;border-radius:4px;max-width:720px"></iframe>';
        $seo = Seo::make('Embed AI regulation widgets: jurisdiction card, deadlines, map', 'Put a live AI regulation card, the upcoming deadlines or the where-AI-is-regulated map on your own site. Server-rendered, under 30 KB, with attribution, no tracking.', route('embed.index'))
            ->withBreadcrumbs([['Home', route('home')], ['Open data', route('open-data')], ['Embed', route('embed.index')]])
            ->withPageType('WebPage');

        return view('site.embed.index', compact('seo', 'kind', 'jurisdiction', 'jurisdictions', 'src', 'snippet', 'height'));
    }

    public function jurisdiction(string $slug): Response
    {
        $j = Jurisdiction::published()->where('slug', $slug)->firstOrFail();
        $policies = PolicyInstrument::published()->where('jurisdiction_id', $j->id)->orderByDesc('is_binding')->orderBy('title')->limit(4)->get();
        $counts = ['instruments' => PolicyInstrument::published()->where('jurisdiction_id', $j->id)->count(), 'binding' => PolicyInstrument::published()->where('jurisdiction_id', $j->id)->where('is_binding', true)->count()];

        return $this->frame(view('site.embed.jurisdiction', compact('j', 'policies', 'counts'))->render());
    }

    public function deadlines(Request $request, PolicyCatalog $catalog): Response
    {
        $slug = preg_match('/^[a-z0-9-]+$/', (string) $request->query('jurisdiction')) ? $request->query('jurisdiction') : null;
        $j = $slug ? Jurisdiction::published()->where('slug', $slug)->first() : null;
        $deadlines = $catalog->upcomingDeadlines(6, $j?->id);

        return $this->frame(view('site.embed.deadlines', compact('deadlines', 'j'))->render());
    }

    public function map(): Response
    {
        $report = StateOfAiRegulation::report(StateOfAiRegulation::currentQuarter());

        return $this->frame(view('site.embed.map', ['tiles' => collect($report['tiles'])->groupBy('region')->sortKeys(), 'quarter' => $report['quarter'], 'totals' => $report['totals']])->render());
    }

    private function frame(string $html): Response
    {
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'public, max-age=900', 'X-Robots-Tag' => 'noindex']);
    }
}
