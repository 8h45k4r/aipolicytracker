<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\DigestIssue;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\View\View;

/**
 * The weekly digest, as an archive. Each issue is recorded when it is sent
 * (SendDigestCommand) and rendered here from the records it carried, so a
 * reader who was not subscribed that week, or a search engine, can still
 * find it.
 */
class NewsletterController extends Controller
{
    public function index(): View
    {
        $issues = DigestIssue::orderByDesc('sent_on')->paginate(24);

        $seo = Seo::make(
            'AI Policy Digest: Weekly Newsletter Archive',
            'Every issue of the weekly AI policy digest: the dated, source-backed changes to AI law and guidance sent to subscribers each week, with upcoming application dates.',
            Seo::pagedUrl(route('newsletter.index'), $issues->currentPage()),
            $issues->total() > 0
        )->withBreadcrumbs([['Home', route('home')], ['Newsletter', route('newsletter.index')]])
            ->withModified($issues->getCollection()->max('created_at'))
            ->withPageType('CollectionPage', [
                'name' => 'AI policy digest archive',
                'mainEntity' => Seo::itemList($issues->getCollection(), fn ($i) => 'AI policy digest, '.$i->sent_on->format('j F Y'), fn ($i) => $i->url()),
            ]);

        return view('site.newsletter.index', compact('seo', 'issues'));
    }

    public function show(DigestIssue $issue): View
    {
        $changes = $issue->changes();
        $deadlines = $issue->deadlines();

        $seo = Seo::make(
            PageTitle::newsletterIssue($issue->sent_on),
            'The AI policy digest for the week to '.$issue->sent_on->format('j F Y').': '.$changes->count().' recorded '.($changes->count() === 1 ? 'change' : 'changes').' with official sources, and the application dates coming up.',
            $issue->url(),
            $issue->isIndexable()
        )->withBreadcrumbs([['Home', route('home')], ['Newsletter', route('newsletter.index')], [$issue->sent_on->format('j M Y'), $issue->url()]])
            ->withPublished($issue->sent_on)
            ->withModified($issue->updated_at)
            ->withOgType('article')
            ->withPageType('Article', [
                'headline' => 'AI policy digest, '.$issue->sent_on->format('j F Y'),
                'articleSection' => 'Newsletter',
                'author' => ['@id' => url('/').'#organization'],
            ]);

        return view('site.newsletter.show', compact('seo', 'issue', 'changes', 'deadlines'));
    }
}
