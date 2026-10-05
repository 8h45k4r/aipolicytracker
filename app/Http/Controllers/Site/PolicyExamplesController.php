<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PolicyInstrument;
use App\Services\Templates\TemplateCatalog;
use App\Support\Seo;
use Illuminate\View\View;

/**
 * /ai-policy-examples: what "an AI policy" looks like, answered with real ones. National AI
 * policies and strategies from the records, grouped by region, newest first, each linking
 * to its record and official source; then the company AI policy template for readers who
 * meant their own organisation's policy.
 */
class PolicyExamplesController extends Controller
{
    public function index(): View
    {
        $policies = PolicyInstrument::published()->with('jurisdiction')
            ->whereIn('instrument_type', ['strategy', 'policy'])
            ->orderByRaw('COALESCE(adopted_on, published_on, in_force_on) DESC')
            ->get();
        $byRegion = $policies->groupBy(fn ($p) => $p->jurisdiction?->region ?: 'International')->sortKeys();
        $recent = $policies->take(5);
        $template = TemplateCatalog::find('acceptable-use-policy');

        $answer = sprintf(
            'National AI policies set out how a government means to develop, use and govern AI. %d are on record from %d jurisdictions; the most recent include %s. For an organisation\'s own AI policy, the rules staff follow when they use AI, see the company AI policy template below.',
            $policies->count(),
            $policies->pluck('jurisdiction_id')->unique()->count(),
            $recent->map(fn ($p) => ($p->short_title ?: $p->title).' ('.$p->jurisdiction?->name.')')->join('; ', ' and '),
        );

        $faq = [
            ['question' => 'What is an example of a national AI policy?', 'answer' => $recent->first() ? ($recent->first()->short_title ?: $recent->first()->title).' ('.$recent->first()->jurisdiction?->name.') is one of the most recent on record. '.trim((string) $recent->first()->summary_plain) : 'See the list on this page.'],
            ['question' => 'What is the difference between an AI policy and an AI law?', 'answer' => 'A national AI policy or strategy states aims and plans and binds no one by itself. An AI law (an act, regulation or binding rule) creates duties with legal force. Each record on this site says which it is, and whether it binds anyone.'],
            ['question' => 'What should a company AI policy include?', 'answer' => 'Its scope, the AI tools staff may use, uses that are not allowed, rules for data and confidential information, disclosure of AI use, training, reporting and enforcement, and a review cycle. The free company AI policy template on this site has those sections and cites the duties they serve.'],
        ];

        $seo = Seo::make(
            'AI Policy Examples: National AI Policies by Country',
            sprintf('%d examples of national AI policies and strategies from %d countries, newest first, each with its status and official source, plus a free company AI policy template.', $policies->count(), $policies->pluck('jurisdiction_id')->unique()->count()),
            route('ai-policy-examples'),
        )->withBreadcrumbs([['Home', route('home')], ['Policies', route('policies.index')], ['AI policy examples', route('ai-policy-examples')]])
            ->withPageType('CollectionPage', ['name' => 'AI policy examples', 'mainEntity' => Seo::itemList($policies->take(50), fn ($p) => $p->title, fn ($p) => $p->url(), 'National AI policies and strategies')])
            ->withFaq($faq);

        return view('site.policies.examples', compact('seo', 'byRegion', 'policies', 'answer', 'template'));
    }
}
