<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\ExternalIncident;
use App\Models\NewsItem;
use App\Services\News\NewsCollector;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * AI policy news in one stream, newest first, from three kinds of source, each labelled:
 * official sources' own feeds (headline, date, publisher, link out: no article text), the
 * tracker's own dated changes, and recorded AI incidents. Filterable by topic and kind.
 */
class NewsController extends Controller
{
    public const KINDS = ['official' => 'Official sources', 'records' => 'Recorded changes', 'incidents' => 'AI incidents'];

    public const PER_PAGE = 40;

    public function index(Request $request): View
    {
        $topics = config('news.topics');
        $topic = array_key_exists((string) $request->query('topic'), $topics) ? (string) $request->query('topic') : null;
        $kind = array_key_exists((string) $request->query('kind'), self::KINDS) ? (string) $request->query('kind') : null;
        $since = now()->subDays(180);

        $items = collect();
        if (! $kind || $kind === 'official') {
            $items = $items->concat(NewsItem::where('published_at', '>=', $since)->orderByDesc('published_at')->limit(300)->get()->map(fn (NewsItem $n) => [
                'kind' => 'official', 'date' => $n->published_at, 'title' => $n->title, 'url' => $n->url, 'external' => true,
                'by' => $n->source()['publisher'], 'jurisdiction' => $n->jurisdiction_slug, 'topics' => $n->topics,
            ]));
        }
        if (! $kind || $kind === 'records') {
            $items = $items->concat(ChangeEvent::published()->with('jurisdiction')->where('occurred_on', '>=', $since->toDateString())->where('occurred_on', '<=', now()->toDateString())
                ->orderByDesc('occurred_on')->limit(200)->get()->reject->isSiteUpdate()->map(fn (ChangeEvent $c) => [
                    'kind' => 'records', 'date' => $c->occurred_on, 'title' => $c->title, 'url' => $c->url(), 'external' => false,
                    'by' => 'AIPolicyTracker change log'.($c->jurisdiction ? ' · '.$c->jurisdiction->name : ''), 'jurisdiction' => $c->jurisdiction?->slug,
                    'topics' => array_values(array_unique(array_merge(['policy'], NewsCollector::topics($c->title.' '.$c->what_changed)))),
                ]));
        }
        if (! $kind || $kind === 'incidents') {
            $items = $items->concat(ExternalIncident::where('occurred_on', '>=', $since->toDateString())->orderByDesc('occurred_on')->limit(200)->get()
                ->filter->isIndexable()->map(fn (ExternalIncident $i) => [
                    'kind' => 'incidents', 'date' => $i->occurred_on, 'title' => $i->title, 'url' => $i->url(), 'external' => false,
                    'by' => 'AI Incident Database, via AIPolicyTracker', 'jurisdiction' => null, 'topics' => ['incidents', 'risk'],
                ]));
        }
        $items = $items->when($topic, fn (Collection $c) => $c->filter(fn ($i) => in_array($topic, $i['topics'], true)))
            ->sortByDesc(fn ($i) => $i['date']->timestamp)->values();

        $page = max(1, (int) $request->query('page', 1));
        $total = $items->count();
        $pageItems = $items->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values();
        abort_if($page > 1 && $pageItems->isEmpty(), 404);
        $counts = ['official' => NewsItem::where('published_at', '>=', $since)->count(), 'sources' => count(config('news.sources'))];

        $indexable = ! $topic && ! $kind && $page === 1;
        $seo = Seo::make('AI policy news from official sources', 'AI policy, safety and governance news from regulators and governments, with the tracker\'s recorded law changes and AI incidents, newest first, each linked to its source.', route('news.index'), $indexable)
            ->withBreadcrumbs([['Home', route('home')], ['News', route('news.index')]])
            ->withPageType('CollectionPage', ['mainEntity' => Seo::itemList($pageItems->take(30), fn ($i) => $i['title'], fn ($i) => $i['url'], 'AI policy news')]);

        return view('site.news.index', compact('seo', 'pageItems', 'total', 'page', 'topics', 'topic', 'kind', 'counts'));
    }
}
